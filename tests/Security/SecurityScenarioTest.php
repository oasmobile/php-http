<?php
declare(strict_types=1);

/**
 * Security module scenario tests.
 *
 * Verifies Security behavior from a user-scenario perspective:
 * construct MicroKernel → configure security → boot → send request → assert response.
 *
 * These tests establish a behavioral baseline for the Silex → Symfony migration
 * audit, complementing existing unit/integration tests with scenario-level coverage.
 *
 * @see SecurityAuthenticationFlowIntegrationTest for existing integration tests
 */

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class SecurityScenarioTest extends ScenarioTestCase
{
    /**
     * Build a base config with routing pointing to scenario routes.
     *
     * @param array<string, mixed> $securityConfig Security configuration to merge
     * @return array<string, mixed>
     */
    private function buildSecurityConfig(array $securityConfig): array
    {
        return [
            'cache_dir'      => static::createTempCacheDir(),
            'routing'        => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
            'view_handlers'  => [new JsonViewHandler()],
            'error_handlers' => [new JsonErrorHandler()],
            'security'       => $securityConfig,
        ];
    }

    /**
     * Build a standard security config with a single firewall and common access rules.
     *
     * @param string $firewallPattern  Regex pattern for the firewall
     * @param array  $accessRules      Access rules array
     * @param array  $roleHierarchy    Role hierarchy array
     * @return array<string, mixed>
     */
    private function buildStandardSecurityConfig(
        string $firewallPattern = '^/scenario',
        array $accessRules = [],
        array $roleHierarchy = [],
    ): array {
        return [
            'policies'       => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls'      => [
                'scenario.main' => new SimpleFirewall([
                    'pattern'  => $firewallPattern,
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
            'access_rules'   => $accessRules,
            'role_hierarchy'  => $roleHierarchy,
        ];
    }

    // -----------------------------------------------------------------
    // R2-AC1: Complete authentication flow
    // -----------------------------------------------------------------

    /**
     * Firewall with pre_auth policy → boot → send request with valid credentials
     * → getToken() returns PostAuthenticationToken, getUser() returns authenticated user.
     */
    public function testCompleteAuthenticationFlow(): void
    {
        $config = $this->buildSecurityConfig(
            $this->buildStandardSecurityConfig(
                accessRules: [
                    ['pattern' => '^/scenario/security', 'roles' => 'ROLE_USER'],
                ],
                roleHierarchy: [
                    'ROLE_ADMIN' => ['ROLE_USER'],
                ],
            ),
        );

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/security/info', ['sig' => 'abcd']);

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        // Verify token type is PostAuthenticationToken
        $this->assertSame(PostAuthenticationToken::class, $data['token_class']);
        // Verify authenticated user
        $this->assertSame('admin', $data['user']);
        $this->assertTrue($data['is_authenticated']);
    }

    // -----------------------------------------------------------------
    // R2-AC2: Authentication failure
    // -----------------------------------------------------------------

    /**
     * Invalid credentials → token remains null → access rule determines outcome.
     * With access rule requiring ROLE_USER → 403.
     */
    public function testAuthenticationFailure(): void
    {
        $config = $this->buildSecurityConfig(
            $this->buildStandardSecurityConfig(
                accessRules: [
                    ['pattern' => '^/scenario/security', 'roles' => 'ROLE_USER'],
                ],
            ),
        );

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/security/info', ['sig' => 'invalid']);

        $this->assertStatusCode($response, Response::HTTP_FORBIDDEN);
    }

    /**
     * No credentials at all → token remains null → access rule determines outcome.
     */
    public function testAuthenticationFailureNoCredentials(): void
    {
        $config = $this->buildSecurityConfig(
            $this->buildStandardSecurityConfig(
                accessRules: [
                    ['pattern' => '^/scenario/security', 'roles' => 'ROLE_USER'],
                ],
            ),
        );

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/security/info');

        $this->assertStatusCode($response, Response::HTTP_FORBIDDEN);
    }

    // -----------------------------------------------------------------
    // R2-AC3: isGranted() with various attributes
    // -----------------------------------------------------------------

    /**
     * IS_AUTHENTICATED_FULLY: authenticated user → granted.
     */
    public function testIsGrantedWithIsAuthenticatedFully(): void
    {
        $config = $this->buildSecurityConfig(
            $this->buildStandardSecurityConfig(
                accessRules: [
                    ['pattern' => '^/scenario/security', 'roles' => 'IS_AUTHENTICATED_FULLY'],
                ],
            ),
        );

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/security/info', ['sig' => 'abcd']);

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertTrue($data['is_authenticated']);
    }

    /**
     * ROLE_ADMIN: admin user has ROLE_ADMIN → granted.
     */
    public function testIsGrantedWithRoleAdmin(): void
    {
        $config = $this->buildSecurityConfig(
            $this->buildStandardSecurityConfig(
                accessRules: [
                    ['pattern' => '^/scenario/admin', 'roles' => 'ROLE_ADMIN'],
                ],
                roleHierarchy: [
                    'ROLE_ADMIN' => ['ROLE_USER'],
                ],
            ),
        );

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/admin/resource', ['sig' => 'abcd']);

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('admin_ok', $data['status']);
        $this->assertTrue($data['admin']);
    }

    /**
     * Role hierarchy inheritance: ROLE_ADMIN inherits ROLE_USER → admin can access ROLE_USER route.
     */
    public function testIsGrantedWithRoleHierarchyInheritance(): void
    {
        $config = $this->buildSecurityConfig(
            $this->buildStandardSecurityConfig(
                accessRules: [
                    ['pattern' => '^/scenario/api', 'roles' => 'ROLE_USER'],
                ],
                roleHierarchy: [
                    'ROLE_ADMIN' => ['ROLE_USER'],
                ],
            ),
        );

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/api/resource', ['sig' => 'abcd']);

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('api_ok', $data['status']);
        $this->assertSame('admin', $data['user']);
    }

    // -----------------------------------------------------------------
    // R2-AC4: Multiple firewall configuration
    // -----------------------------------------------------------------

    /**
     * Two firewalls with different URL patterns → each applies only to its matched pattern.
     */
    public function testMultipleFirewallConfiguration(): void
    {
        $config = $this->buildSecurityConfig([
            'policies'  => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'scenario.api' => new SimpleFirewall([
                    'pattern'  => '^/scenario/api',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
                'scenario.admin' => new SimpleFirewall([
                    'pattern'  => '^/scenario/admin',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
            'access_rules' => [
                ['pattern' => '^/scenario/api', 'roles' => 'ROLE_USER'],
                ['pattern' => '^/scenario/admin', 'roles' => 'ROLE_ADMIN'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
            ],
        ]);

        $kernel = $this->buildKernel($config);

        // Admin user (ROLE_ADMIN) can access /scenario/api (requires ROLE_USER, inherited)
        $response = $this->handleRequest($kernel, 'GET', '/scenario/api/resource', ['sig' => 'abcd']);
        $this->assertJsonResponse($response, Response::HTTP_OK);

        // Need a fresh kernel for the second request (kernel is single-use after boot)
        $kernel2  = $this->buildKernel($config);
        $response = $this->handleRequest($kernel2, 'GET', '/scenario/admin/resource', ['sig' => 'abcd']);
        $data     = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('admin_ok', $data['status']);

        // Child user (ROLE_CHILD) can access /scenario/api (ROLE_CHILD inherits ROLE_USER)
        // but cannot access /scenario/admin (requires ROLE_ADMIN)
        $configWithChildHierarchy = $this->buildSecurityConfig([
            'policies'  => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'scenario.api' => new SimpleFirewall([
                    'pattern'  => '^/scenario/api',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
                'scenario.admin' => new SimpleFirewall([
                    'pattern'  => '^/scenario/admin',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
            'access_rules' => [
                ['pattern' => '^/scenario/api', 'roles' => 'ROLE_USER'],
                ['pattern' => '^/scenario/admin', 'roles' => 'ROLE_ADMIN'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
                'ROLE_CHILD' => ['ROLE_USER'],
            ],
        ]);

        $kernel3  = $this->buildKernel($configWithChildHierarchy);
        $response = $this->handleRequest($kernel3, 'GET', '/scenario/api/resource', ['sig' => 'child']);
        $this->assertJsonResponse($response, Response::HTTP_OK);

        $kernel4  = $this->buildKernel($configWithChildHierarchy);
        $response = $this->handleRequest($kernel4, 'GET', '/scenario/admin/resource', ['sig' => 'child']);
        $this->assertStatusCode($response, Response::HTTP_FORBIDDEN);
    }

    // -----------------------------------------------------------------
    // R2-AC5: Multiple access rule ordering
    // -----------------------------------------------------------------

    /**
     * Multiple access rules → matched in registration order, first match takes effect.
     *
     * Rule 1: /scenario/api → ROLE_ADMIN (more restrictive, registered first)
     * Rule 2: /scenario     → ROLE_USER  (less restrictive, registered second)
     *
     * Child user (ROLE_CHILD inherits ROLE_USER) accessing /scenario/api
     * → first rule matches → requires ROLE_ADMIN → denied.
     */
    public function testMultipleAccessRuleOrdering(): void
    {
        $config = $this->buildSecurityConfig([
            'policies'  => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'scenario.main' => new SimpleFirewall([
                    'pattern'  => '^/scenario',
                    'policies' => ['mauth' => true],
                    'users'    => new TestApiUserProvider(),
                ]),
            ],
            'access_rules' => [
                // More restrictive rule first
                ['pattern' => '^/scenario/api', 'roles' => 'ROLE_ADMIN'],
                // Less restrictive rule second
                ['pattern' => '^/scenario', 'roles' => 'ROLE_USER'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
                'ROLE_CHILD' => ['ROLE_USER'],
            ],
        ]);

        // Child user (ROLE_CHILD, inherits ROLE_USER but not ROLE_ADMIN)
        // accessing /scenario/api → first rule matches → ROLE_ADMIN required → 403
        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/api/resource', ['sig' => 'child']);
        $this->assertStatusCode($response, Response::HTTP_FORBIDDEN);

        // Child user accessing /scenario/public → second rule matches → ROLE_USER → 200
        $kernel2  = $this->buildKernel($config);
        $response = $this->handleRequest($kernel2, 'GET', '/scenario/public', ['sig' => 'child']);
        $this->assertJsonResponse($response, Response::HTTP_OK);

        // Admin user accessing /scenario/api → first rule matches → ROLE_ADMIN → 200
        $kernel3  = $this->buildKernel($config);
        $response = $this->handleRequest($kernel3, 'GET', '/scenario/api/resource', ['sig' => 'abcd']);
        $this->assertJsonResponse($response, Response::HTTP_OK);
    }

    // -----------------------------------------------------------------
    // R2-AC6: Unauthenticated access to protected resource
    // -----------------------------------------------------------------

    /**
     * Unauthenticated request to a protected resource → AccessDeniedHttpException → 403.
     */
    public function testUnauthenticatedAccessToProtectedResource(): void
    {
        $config = $this->buildSecurityConfig(
            $this->buildStandardSecurityConfig(
                accessRules: [
                    ['pattern' => '^/scenario/security', 'roles' => 'ROLE_USER'],
                ],
            ),
        );

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/security/info');

        $this->assertStatusCode($response, Response::HTTP_FORBIDDEN);
    }

    // -----------------------------------------------------------------
    // R2-AC7: Stateless behavior (v3.x is stateless-only)
    // -----------------------------------------------------------------

    /**
     * v3.x is stateless-only — no session interaction during authentication.
     * Verifies that the firewall processes authentication without requiring
     * or creating a session. The response should not contain a session cookie.
     */
    public function testStatelessFirewallBehavior(): void
    {
        $config = $this->buildSecurityConfig([
            'policies'  => [
                'mauth' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'scenario.main' => new SimpleFirewall([
                    'pattern'   => '^/scenario',
                    'policies'  => ['mauth' => true],
                    'users'     => new TestApiUserProvider(),
                ]),
            ],
            'access_rules' => [
                ['pattern' => '^/scenario/security', 'roles' => 'ROLE_USER'],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
            ],
        ]);

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest($kernel, 'GET', '/scenario/security/info', ['sig' => 'abcd']);

        $data = $this->assertJsonResponse($response, Response::HTTP_OK);
        $this->assertSame('admin', $data['user']);

        // Verify no session cookie is set in the response
        $sessionCookies = array_filter(
            $response->headers->getCookies(),
            fn($cookie) => str_contains(strtolower($cookie->getName()), 'sess'),
        );
        $this->assertEmpty($sessionCookies, 'Stateless firewall should not set session cookies');
    }
}
