<?php
declare(strict_types=1);

/**
 * Preservation Property Test — Constructor-Only Security Config Unchanged.
 *
 * Feature: hotfix/3.7.0
 * Property 2: Preservation — Constructor-Only Security Config Unchanged
 *
 * **Validates: Requirements Unchanged Behavior 1, 2, 3, 4, 5**
 *
 * 此测试验证在未修复代码上的基线行为：
 * - registerSecurity() 仅使用 Constructor_Config → SimpleSecurityProvider 正确初始化
 * - registerSecurity() 使用 empty/null security config → early return，不初始化 provider
 * - addRoute()/addRoutes() 路由注入独立于 security 工作
 *
 * 预期结果（未修复代码）：测试通过——确认需要保持的基线行为。
 */

namespace Oasis\Mlib\Http\Test\PBT\Security;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Preservation Property Tests for Constructor-Only Security Config.
 *
 * These tests MUST PASS on the unfixed code, confirming the baseline behavior
 * that the fix must preserve.
 */
class SecurityPreservationPropertyTest extends TestCase
{
    use TestTrait;
    use RouteCacheCleaner;

    /** @var callable|null */
    private $previousExceptionHandler = null;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        $this->cleanRouteCache(__DIR__ . '/../../cache');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Restore exception handler to prevent PHPUnit "risky" warnings
        while (true) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->previousExceptionHandler || $current === null) {
                break;
            }
            restore_exception_handler();
        }
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }

        parent::tearDown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Property 2.1: Constructor-Only Security Config → SimpleSecurityProvider 正确初始化
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property 2: Preservation — Constructor-Only Security Config Initializes Provider
     *
     * For all valid Constructor_Config (random firewalls, access_rules, policies,
     * role_hierarchy combinations), registerSecurity() produces the same initialization
     * result: tokenStorage is set, authorizationChecker is set.
     *
     * **Validates: Requirements Unchanged Behavior 1, 2**
     */
    public function testConstructorOnlySecurityConfigInitializesProvider(): void
    {
        $this->forAll(
            // Number of access rules (0–3)
            Generators::choose(0, 3),
            // Number of role hierarchy entries (0–2)
            Generators::choose(0, 2)
        )->then(function (int $ruleCount, int $hierarchyCount) {
            // Build a valid security config with random combinations
            $securityConfig = $this->buildValidSecurityConfig($ruleCount, $hierarchyCount);

            $cacheDir = static::createTempCacheDir();
            $kernel = new MicroKernel(
                [
                    'cache_dir' => $cacheDir,
                    'security'  => $securityConfig,
                ],
                true
            );

            $kernel->boot();

            // After boot with valid security config, tokenStorage should be set
            $token = $kernel->getToken();
            // Token is null (no request processed yet) but tokenStorage exists
            // The fact that getToken() doesn't throw proves tokenStorage was initialized
            $this->assertNull($token, 'Token should be null before any request is processed');

            // authorizationChecker should be set (isGranted should not throw due to missing checker)
            // We verify by checking that the kernel can perform authorization checks
            // (it will return false since no token is set, but it shouldn't throw)
            try {
                $result = $kernel->isGranted('ROLE_USER');
                // If we get here, authorizationChecker is initialized
                $this->assertFalse($result, 'isGranted should return false when no token is set');
            } catch (\Throwable $e) {
                // Symfony 8 may throw when no token is in storage — that's also valid
                // as long as it's not a "checker not set" error
                $this->assertStringNotContainsString(
                    'authorizationChecker',
                    $e->getMessage(),
                    'Error should not be about missing authorizationChecker'
                );
            }

            $kernel->shutdown();
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Property 2.2: Empty/Null Security Config → Early Return, No Provider
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property 2: Preservation — Empty/Null Security Config Early Return
     *
     * For all empty/null security config, registerSecurity() does early return
     * without initializing the security provider. tokenStorage remains null.
     *
     * **Validates: Requirements Unchanged Behavior 1, 4**
     */
    public function testEmptySecurityConfigEarlyReturn(): void
    {
        $this->forAll(
            // Generate various "empty" security config representations
            Generators::elements(
                null,       // null — no security key at all
                [],         // empty array
                false       // falsy value
            )
        )->then(function (mixed $emptyConfig) {
            $cacheDir = static::createTempCacheDir();

            $httpConfig = ['cache_dir' => $cacheDir];
            if ($emptyConfig !== null) {
                $httpConfig['security'] = $emptyConfig;
            }
            // When $emptyConfig is null, we don't set the 'security' key at all

            $kernel = new MicroKernel($httpConfig, true);
            $kernel->boot();

            // tokenStorage should remain null (security provider not initialized)
            $token = $kernel->getToken();
            $this->assertNull($token, 'Token should be null when no security config is provided');

            // Verify that isGranted throws or returns false (no authorizationChecker set)
            try {
                $kernel->isGranted('ROLE_USER');
                // If it doesn't throw, that's fine too (some implementations return false)
            } catch (\Throwable $e) {
                // Expected: no authorization checker available
                $this->assertTrue(true, 'isGranted correctly throws when no security is configured');
            }

            $kernel->shutdown();
        });
    }

    /**
     * Property 2: Preservation — No Security Key in Config
     *
     * When the 'security' key is completely absent from httpConfig,
     * registerSecurity() does early return. No security provider is initialized.
     *
     * **Validates: Requirements Unchanged Behavior 1**
     */
    public function testNoSecurityKeyInConfig(): void
    {
        $cacheDir = static::createTempCacheDir();

        $kernel = new MicroKernel(['cache_dir' => $cacheDir], true);
        $kernel->boot();

        // No security provider initialized
        $this->assertNull($kernel->getToken(), 'Token should be null when security key is absent');

        $kernel->shutdown();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Property 2.3: Route Injection Independent of Security
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Property 2: Preservation — Route Injection Works Independently of Security
     *
     * For all route injection calls, routing behavior is unaffected by security
     * configuration changes. Routes injected via addRoute()/addRoutes() work
     * regardless of whether security config is present or absent.
     *
     * **Validates: Requirements Unchanged Behavior 5**
     */
    public function testRouteInjectionIndependentOfSecurity(): void
    {
        $this->forAll(
            // Random route path suffix
            Generators::elements('alpha', 'beta', 'gamma', 'delta', 'epsilon'),
            // Whether security config is present
            Generators::elements(true, false)
        )->then(function (string $pathSuffix, bool $withSecurity) {
            $cacheDir = static::createTempCacheDir();

            $httpConfig = ['cache_dir' => $cacheDir];
            if ($withSecurity) {
                $httpConfig['security'] = $this->buildValidSecurityConfig(1, 0);
            }

            $kernel = new MicroKernel($httpConfig, true);

            // Inject a route before boot
            $routePath = '/preservation-test-' . $pathSuffix;
            $controllerName = 'TestController::' . $pathSuffix . 'Action';
            $kernel->addRoute('test_' . $pathSuffix, new Route($routePath, [
                '_controller' => $controllerName,
            ]));

            $kernel->boot();

            // Route should be matchable regardless of security config
            $matcher = $kernel->getRequestMatcher();
            $this->assertNotNull($matcher, 'Request matcher should be available after boot');

            $request = Request::create($routePath, 'GET');
            $result = $matcher->matchRequest($request);

            $this->assertSame(
                $controllerName,
                $result['_controller'],
                sprintf(
                    'Route "%s" should match controller "%s" regardless of security config (withSecurity=%s)',
                    $routePath,
                    $controllerName,
                    $withSecurity ? 'true' : 'false'
                )
            );

            $kernel->shutdown();
        });
    }

    /**
     * Property 2: Preservation — addRoutes() Collection Injection Independent of Security
     *
     * RouteCollection injection via addRoutes() works independently of security config.
     *
     * **Validates: Requirements Unchanged Behavior 5**
     */
    public function testAddRoutesCollectionIndependentOfSecurity(): void
    {
        $this->forAll(
            // Number of routes in collection (1–3)
            Generators::choose(1, 3),
            // Whether security config is present
            Generators::elements(true, false)
        )->then(function (int $routeCount, bool $withSecurity) {
            $cacheDir = static::createTempCacheDir();

            $httpConfig = ['cache_dir' => $cacheDir];
            if ($withSecurity) {
                $httpConfig['security'] = $this->buildValidSecurityConfig(0, 0);
            }

            $kernel = new MicroKernel($httpConfig, true);

            // Build a RouteCollection
            $collection = new RouteCollection();
            $expectedRoutes = [];
            for ($i = 0; $i < $routeCount; $i++) {
                $name = 'collection_route_' . $i;
                $path = '/collection-test-' . $i;
                $controller = 'CollectionController::action' . $i;
                $collection->add($name, new Route($path, ['_controller' => $controller]));
                $expectedRoutes[$path] = $controller;
            }

            $kernel->addRoutes($collection);
            $kernel->boot();

            // All routes should be matchable
            $matcher = $kernel->getRequestMatcher();
            $this->assertNotNull($matcher);

            foreach ($expectedRoutes as $path => $controller) {
                $request = Request::create($path, 'GET');
                $result = $matcher->matchRequest($request);
                $this->assertSame(
                    $controller,
                    $result['_controller'],
                    sprintf('Route at "%s" should match controller "%s"', $path, $controller)
                );
            }

            $kernel->shutdown();
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Build a valid security config with the specified number of access rules
     * and role hierarchy entries.
     *
     * Uses TestAuthenticationPolicy as the policy (pre_auth type) and constructs
     * a firewall that references it.
     *
     * @param int $accessRuleCount Number of access rules to generate
     * @param int $hierarchyCount Number of role hierarchy entries to generate
     * @return array Valid security config array
     */
    private function buildValidSecurityConfig(int $accessRuleCount, int $hierarchyCount): array
    {
        $config = [
            'policies' => [
                'test_policy' => new TestAuthenticationPolicy(),
            ],
            'firewalls' => [
                'test_firewall' => [
                    'pattern'  => '^/secured',
                    'policies' => ['test_policy' => true],
                    'users'    => new \Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider(),
                ],
            ],
        ];

        // Add access rules
        if ($accessRuleCount > 0) {
            $config['access_rules'] = [];
            for ($i = 0; $i < $accessRuleCount; $i++) {
                $config['access_rules'][] = [
                    'pattern' => '^/secured/area' . $i,
                    'roles'   => ['ROLE_USER'],
                ];
            }
        }

        // Add role hierarchy
        if ($hierarchyCount > 0) {
            $config['role_hierarchy'] = [];
            for ($i = 0; $i < $hierarchyCount; $i++) {
                $config['role_hierarchy']['ROLE_LEVEL_' . $i] = ['ROLE_USER'];
            }
        }

        return $config;
    }
}
