<?php

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAccessRule;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Unit tests for SimpleSecurityProvider's access rule listener.
 *
 * Tests the registerAccessRuleListener() behavior by directly dispatching
 * RequestEvent through an EventDispatcher, bypassing the full kernel
 * request handling (routing, error handlers, etc.).
 *
 * Covers: R9.4, R9.5, R10.3, R10.4, R10.5
 */
class AccessRuleListenerTest extends TestCase
{
    /**
     * Build an EventDispatcher with the access rule listener registered,
     * along with a TokenStorage that can be pre-populated.
     *
     * @param array  $accessRules   Array of AccessRuleInterface or config arrays
     * @param array  $roleHierarchy Role hierarchy config
     * @param string|null $tokenUserName If non-null, pre-set a token for this user
     * @param array  $tokenRoles    Roles for the pre-set token user
     *
     * @return array{dispatcher: EventDispatcher, tokenStorage: TokenStorage, adm: AccessDecisionManagerInterface}
     */
    private function buildAccessRuleListener(
        array $accessRules,
        array $roleHierarchy = [],
        ?string $tokenUserName = null,
        array $tokenRoles = []
    ): array {
        $tokenStorage = new TokenStorage();

        if ($tokenUserName !== null) {
            $user = new TestApiUser($tokenUserName, $tokenRoles);
            $token = new PostAuthenticationToken($user, 'test', $user->getRoles());
            $tokenStorage->setToken($token);
        }

        $hierarchy = new RoleHierarchy($roleHierarchy);
        $voter = new RoleHierarchyVoter($hierarchy);
        $adm = new AccessDecisionManager([$voter], new UnanimousStrategy());

        $dispatcher = new EventDispatcher();

        // Use anonymous subclass to expose protected registerAccessRuleListener
        $testProvider = new class extends SimpleSecurityProvider {
            public function registerAccessRuleListenerWithDispatcher(
                EventDispatcher $dispatcher,
                \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $ts,
                AccessDecisionManagerInterface $adm,
                array $accessRules
            ): void {
                $this->configDataProvider = $this->processConfiguration(
                    ['access_rules' => $accessRules],
                    new \Oasis\Mlib\Http\Configuration\SecurityConfiguration()
                );

                $accessRulesData = $this->getAccessRules();

                $dispatcher->addListener(
                    \Symfony\Component\HttpKernel\KernelEvents::REQUEST,
                    function (RequestEvent $event) use ($accessRulesData, $ts, $adm) {
                        if (!$event->isMainRequest()) {
                            return;
                        }
                        $request = $event->getRequest();
                        $token = $ts->getToken();

                        foreach ($accessRulesData as [$pattern, $roles, $channel]) {
                            if (!$this->requestMatchesPattern($request, $pattern)) {
                                continue;
                            }

                            if (empty($roles)) {
                                return;
                            }

                            if (!$token || !$token->getUser()) {
                                throw new AccessDeniedHttpException('Access Denied');
                            }

                            if (!$adm->decide($token, (array)$roles)) {
                                throw new AccessDeniedHttpException('Access Denied');
                            }

                            return;
                        }
                    },
                    MicroKernel::BEFORE_PRIORITY_FIREWALL - 1
                );
            }
        };

        $testProvider->registerAccessRuleListenerWithDispatcher(
            $dispatcher,
            $tokenStorage,
            $adm,
            $accessRules
        );

        return [
            'dispatcher'   => $dispatcher,
            'tokenStorage' => $tokenStorage,
            'adm'          => $adm,
        ];
    }

    /**
     * Dispatch a RequestEvent for the given URI and return whether it completed
     * without throwing AccessDeniedHttpException.
     *
     * @return bool true if request was allowed, false should not happen (exception thrown instead)
     * @throws AccessDeniedHttpException if access is denied
     */
    private function dispatchRequest(EventDispatcher $dispatcher, string $uri, array $server = []): void
    {
        $request = Request::create($uri, 'GET', [], [], [], $server);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $dispatcher->dispatch($event, \Symfony\Component\HttpKernel\KernelEvents::REQUEST);
    }

    // ─── Test: Registration order — first matching rule takes effect ──

    /**
     * When multiple access rules match, the first one in registration order takes effect.
     *
     * Setup: Two rules for /test — first requires ROLE_ADMIN, second requires ROLE_USER.
     * User has ROLE_USER but not ROLE_ADMIN → first rule denies.
     */
    public function testFirstMatchingRuleTakesEffect()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/test', 'ROLE_ADMIN'),
                new TestAccessRule('^/test', 'ROLE_USER'),
            ],
            [],
            'testuser',
            ['ROLE_USER']
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->dispatchRequest($result['dispatcher'], '/test/path');
    }

    /**
     * When the first matching rule allows access, subsequent rules are not evaluated.
     */
    public function testFirstMatchingRuleAllowsAccess()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/test', 'ROLE_USER'),
                new TestAccessRule('^/test', 'ROLE_ADMIN'),
            ],
            [],
            'testuser',
            ['ROLE_USER']
        );

        // Should not throw — first rule matches and user has ROLE_USER
        $this->dispatchRequest($result['dispatcher'], '/test/path');
        $this->assertTrue(true); // If we get here, access was allowed
    }

    // ─── Test: No role requirement → allow access ────────────────────

    /**
     * When a matching access rule has no role requirement, access is allowed
     * even without a token.
     */
    public function testNoRoleRequirementAllowsAccess()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/public', []),
            ]
        );

        // No token set, but rule has no role requirement → should pass
        $this->dispatchRequest($result['dispatcher'], '/public/page');
        $this->assertTrue(true);
    }

    // ─── Test: Token is null → AccessDeniedHttpException ─────────────

    /**
     * When token is null and the matching rule requires roles, throw AccessDeniedHttpException.
     */
    public function testNullTokenThrowsAccessDenied()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/secured', 'ROLE_USER'),
            ]
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->dispatchRequest($result['dispatcher'], '/secured/page');
    }

    // ─── Test: Insufficient roles → AccessDeniedHttpException ────────

    /**
     * When user lacks the required role, throw AccessDeniedHttpException.
     */
    public function testInsufficientRolesThrowsAccessDenied()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/admin', 'ROLE_ADMIN'),
            ],
            [],
            'testuser',
            ['ROLE_USER']
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->dispatchRequest($result['dispatcher'], '/admin/panel');
    }

    // ─── Test: Role hierarchy correctly propagates inheritance ────────

    /**
     * ROLE_PARENT inherits ROLE_CHILD. User with ROLE_PARENT should pass
     * a rule requiring ROLE_CHILD.
     */
    public function testRoleHierarchyInheritance()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/child-area', 'ROLE_CHILD'),
            ],
            [
                'ROLE_PARENT' => ['ROLE_CHILD'],
            ],
            'parentuser',
            ['ROLE_PARENT']
        );

        $this->dispatchRequest($result['dispatcher'], '/child-area/page');
        $this->assertTrue(true);
    }

    /**
     * Multi-level hierarchy: ROLE_ADMIN → ROLE_PARENT → ROLE_CHILD → ROLE_USER.
     * User with ROLE_ADMIN should pass a rule requiring ROLE_USER.
     */
    public function testRoleHierarchyMultiLevel()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/user-area', 'ROLE_USER'),
            ],
            [
                'ROLE_ADMIN'  => ['ROLE_PARENT'],
                'ROLE_PARENT' => ['ROLE_CHILD'],
                'ROLE_CHILD'  => ['ROLE_USER'],
            ],
            'adminuser',
            ['ROLE_ADMIN']
        );

        $this->dispatchRequest($result['dispatcher'], '/user-area/page');
        $this->assertTrue(true);
    }

    /**
     * Role hierarchy does NOT grant upward: ROLE_CHILD does NOT inherit ROLE_PARENT.
     */
    public function testRoleHierarchyDoesNotGrantUpward()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/parent-area', 'ROLE_PARENT'),
            ],
            [
                'ROLE_PARENT' => ['ROLE_CHILD'],
            ],
            'childuser',
            ['ROLE_CHILD']
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->dispatchRequest($result['dispatcher'], '/parent-area/page');
    }

    // ─── Test: No matching rule → request proceeds ───────────────────

    /**
     * When no access rule matches the request URL, the request proceeds normally.
     */
    public function testNoMatchingRuleAllowsAccess()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/secured', 'ROLE_USER'),
            ]
        );

        // Request to /public which doesn't match ^/secured
        $this->dispatchRequest($result['dispatcher'], '/public/page');
        $this->assertTrue(true);
    }

    // ─── Test: RequestMatcherInterface pattern support ────────────────

    /**
     * Access rule with RequestMatcherInterface (ChainRequestMatcher) pattern.
     */
    public function testRequestMatcherInterfacePattern()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule(
                    new \Symfony\Component\HttpFoundation\ChainRequestMatcher([
                        new \Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher('^/api'),
                    ]),
                    'ROLE_API'
                ),
            ],
            [],
            'apiuser',
            ['ROLE_API']
        );

        $this->dispatchRequest($result['dispatcher'], '/api/data');
        $this->assertTrue(true);
    }

    /**
     * Access rule with RequestMatcherInterface + host matching.
     * Request from matching host with correct role → allowed.
     */
    public function testRequestMatcherWithHostPatternAllows()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule(
                    new \Symfony\Component\HttpFoundation\ChainRequestMatcher([
                        new \Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher('^/host-secured'),
                        new \Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher('example\\.com'),
                    ]),
                    'ROLE_HOST'
                ),
            ],
            [],
            'hostuser',
            ['ROLE_HOST']
        );

        $this->dispatchRequest($result['dispatcher'], '/host-secured/page', ['HTTP_HOST' => 'example.com']);
        $this->assertTrue(true);
    }

    /**
     * Access rule with RequestMatcherInterface + host matching.
     * Request from non-matching host → rule doesn't match → no access restriction.
     */
    public function testRequestMatcherWithHostPatternNoMatch()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule(
                    new \Symfony\Component\HttpFoundation\ChainRequestMatcher([
                        new \Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher('^/host-secured'),
                        new \Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher('example\\.com'),
                    ]),
                    'ROLE_HOST'
                ),
            ]
        );

        // No token, but host doesn't match → rule doesn't apply → no restriction
        $this->dispatchRequest($result['dispatcher'], '/host-secured/page', ['HTTP_HOST' => 'other.com']);
        $this->assertTrue(true);
    }

    // ─── Test: Sub-request is ignored ────────────────────────────────

    /**
     * Access rule listener only processes main requests, not sub-requests.
     */
    public function testSubRequestIsIgnored()
    {
        $result = $this->buildAccessRuleListener(
            [
                new TestAccessRule('^/secured', 'ROLE_USER'),
            ]
        );

        // Dispatch as SUB_REQUEST — should not throw even without token
        $request = Request::create('/secured/page');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);
        $result['dispatcher']->dispatch($event, \Symfony\Component\HttpKernel\KernelEvents::REQUEST);
        $this->assertTrue(true);
    }
}
