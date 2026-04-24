<?php

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 11:01
 */
class SilexKernelTest extends TestCase
{
    use RouteCacheCleaner;

    /** @var int|null saved trusted header set before each test */
    private $savedTrustedHeaderSet;
    /** @var array saved trusted proxies before each test */
    private $savedTrustedProxies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanRouteCache(__DIR__ . '/cache');
        $this->savedTrustedProxies   = Request::getTrustedProxies();
        $this->savedTrustedHeaderSet = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        // Restore global Request state
        Request::setTrustedProxies($this->savedTrustedProxies, $this->savedTrustedHeaderSet);

        // Restore exception handlers that Symfony Kernel may have set
        restore_exception_handler();

        parent::tearDown();
    }

    public function testCreationWithOkConfig()
    {
        require __DIR__ . '/app.php';
    }
    
    public function testProductionMode()
    {
        $config = [];
        $kernel = new MicroKernel($config, false);
        // MicroKernel should be constructable in production mode
        $this->assertInstanceOf(MicroKernel::class, $kernel);
    }
    
    public function testCreationWithWrongConfiguration()
    {
        $config = [
            'routing2' => [
                'path'       => __DIR__ . "/routes.yml",
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test',
                ],
            ],
        ];
        
        $this->expectException(InvalidConfigurationException::class);
        
        new MicroKernel($config, true);
    }

    // ---------------------------------------------------------------
    // Bootstrap_Config — trusted_proxies
    // ---------------------------------------------------------------

    public function testConfigTrustedProxiesMergesIntoRequestTrustedProxies()
    {
        // Reset to known state
        Request::setTrustedProxies([], Request::getTrustedHeaderSet());

        new MicroKernel(['trusted_proxies' => ['10.0.0.1', '10.0.0.2']], true);

        $proxies = Request::getTrustedProxies();
        $this->assertContains('10.0.0.1', $proxies);
        $this->assertContains('10.0.0.2', $proxies);
    }

    // ---------------------------------------------------------------
    // Bootstrap_Config — trusted_header_set
    // ---------------------------------------------------------------

    public function testConfigTrustedHeaderSetWithStringConstant()
    {
        new MicroKernel(['trusted_header_set' => 'HEADER_X_FORWARDED_FOR'], true);

        $this->assertEquals(
            Request::HEADER_X_FORWARDED_FOR,
            Request::getTrustedHeaderSet()
        );
    }

    public function testConfigTrustedHeaderSetWithIntegerPassThrough()
    {
        new MicroKernel(['trusted_header_set' => Request::HEADER_X_FORWARDED_FOR], true);

        $this->assertEquals(
            Request::HEADER_X_FORWARDED_FOR,
            Request::getTrustedHeaderSet()
        );
    }

    // ---------------------------------------------------------------
    // Bootstrap_Config — middlewares validation
    // ---------------------------------------------------------------

    public function testConfigMiddlewaresValidMiddleware()
    {
        $middleware = new TestMiddleware();

        // Should not throw
        $app = new MicroKernel(['middlewares' => [$middleware]], true);
        $this->assertInstanceOf(MicroKernel::class, $app);
    }

    public function testConfigMiddlewaresInvalidValueThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        new MicroKernel(['middlewares' => ['not_a_middleware']], true);
    }

    // ---------------------------------------------------------------
    // Bootstrap_Config — view_handlers validation
    // ---------------------------------------------------------------

    public function testConfigViewHandlersValidCallable()
    {
        // Should not throw — JsonViewHandler is callable
        $app = new MicroKernel(['view_handlers' => [new JsonViewHandler()]], true);
        $this->assertInstanceOf(MicroKernel::class, $app);
    }

    public function testConfigViewHandlersInvalidValueThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        new MicroKernel(['view_handlers' => ['not_callable_string_that_does_not_exist_as_function']], true);
    }

    // ---------------------------------------------------------------
    // Bootstrap_Config — error_handlers validation
    // ---------------------------------------------------------------

    public function testConfigErrorHandlersValidCallable()
    {
        $handler = function () { return null; };
        $app = new MicroKernel(['error_handlers' => [$handler]], true);
        $this->assertInstanceOf(MicroKernel::class, $app);
    }

    public function testConfigErrorHandlersInvalidValueThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        new MicroKernel(['error_handlers' => ['not_callable_string_that_does_not_exist_as_function']], true);
    }

    // ---------------------------------------------------------------
    // Bootstrap_Config — injected_args
    // ---------------------------------------------------------------

    public function testConfigInjectedArgsAddsToControllerInjectedArgs()
    {
        $handler = new JsonViewHandler();
        $app = new MicroKernel(['injected_args' => [$handler]], true);

        $injections = $app->getControllerInjectedArgs();
        $this->assertContains($handler, $injections);
    }

    // ---------------------------------------------------------------
    // boot() — conditional registration
    // ---------------------------------------------------------------

    public function testBootWithRoutingConfigRegistersRouting()
    {
        $app = new MicroKernel(
            [
                'cache_dir' => __DIR__ . '/cache',
                'routing'   => [
                    'path'       => __DIR__ . '/routes.yml',
                    'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
                ],
            ],
            true
        );

        $app->boot();

        // After boot with routing config, request matcher should be available
        $this->assertNotNull($app->getRequestMatcher());
    }

    public function testBootWithTwigConfigRegistersSimpleTwigServiceProvider()
    {
        $app = new MicroKernel(
            [
                'twig' => [
                    'template_dir' => __DIR__ . '/Integration/templates',
                ],
            ],
            true
        );

        $app->boot();

        $this->assertInstanceOf(\Twig\Environment::class, $app->getTwig());
    }

    public function testBootWithoutOptionalConfigsDoesNotRegisterOptionalProviders()
    {
        $app = new MicroKernel([], true);
        $app->boot();

        // Without twig config, twig should not be set
        $this->assertNull($app->getTwig());
    }

    public function testBootDoubleBootProtection()
    {
        $app = new MicroKernel([], true);
        $app->boot();
        // Second boot should be a no-op (early return)
        $app->boot();
        $this->assertTrue(true, 'Double boot should not throw');
    }

    // ---------------------------------------------------------------
    // isGranted()
    // ---------------------------------------------------------------

    public function testIsGrantedReturnsFalseWhenNoAuthorizationChecker()
    {
        $app = new MicroKernel([], true);

        // No authorization checker set
        $this->assertFalse($app->isGranted('ROLE_ADMIN'));
    }

    public function testIsGrantedReturnsFalseOnAuthenticationCredentialsNotFoundException()
    {
        $app = new MicroKernel([], true);

        $checker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $checker->method('isGranted')
            ->willThrowException(new AuthenticationCredentialsNotFoundException('No credentials'));

        $app->setAuthorizationChecker($checker);

        $this->assertFalse($app->isGranted('ROLE_ADMIN'));
    }

    public function testIsGrantedReturnsTrueWhenCheckerGrantsAccess()
    {
        $app = new MicroKernel([], true);

        $checker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $checker->method('isGranted')->willReturn(true);

        $app->setAuthorizationChecker($checker);

        $this->assertTrue($app->isGranted('ROLE_USER'));
    }

    public function testIsGrantedReturnsFalseWhenCheckerDeniesAccess()
    {
        $app = new MicroKernel([], true);

        $checker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $checker->method('isGranted')->willReturn(false);

        $app->setAuthorizationChecker($checker);

        $this->assertFalse($app->isGranted('ROLE_ADMIN'));
    }

    // ---------------------------------------------------------------
    // getCacheDirectories()
    // ---------------------------------------------------------------

    public function testGetCacheDirectoriesNoCacheDir()
    {
        $app = new MicroKernel([], true);

        $this->assertEquals([], $app->getCacheDirectories());
    }

    public function testGetCacheDirectoriesWithCacheDir()
    {
        $app = new MicroKernel(['cache_dir' => '/tmp/test-cache'], true);

        $dirs = $app->getCacheDirectories();
        $this->assertContains('/tmp/test-cache', $dirs);
    }

    public function testGetCacheDirectoriesWithRoutingCacheDir()
    {
        $app = new MicroKernel(
            [
                'cache_dir' => '/tmp/test-cache',
                'routing'   => [
                    'path'      => __DIR__ . '/routes.yml',
                    'cache_dir' => '/tmp/routing-cache',
                ],
            ],
            true
        );

        $dirs = $app->getCacheDirectories();
        $this->assertContains('/tmp/test-cache', $dirs);
        $this->assertContains('/tmp/routing-cache', $dirs);
    }

    public function testGetCacheDirectoriesWithTwigCacheDir()
    {
        $app = new MicroKernel(
            [
                'cache_dir' => '/tmp/test-cache',
                'twig'      => [
                    'cache_dir' => '/tmp/twig-cache',
                ],
            ],
            true
        );

        $dirs = $app->getCacheDirectories();
        $this->assertContains('/tmp/test-cache', $dirs);
        $this->assertContains('/tmp/twig-cache', $dirs);
    }

    // ---------------------------------------------------------------
    // getParameter()
    // ---------------------------------------------------------------

    public function testGetParameterFromExtraParameters()
    {
        $app = new MicroKernel([], true);
        $app->addExtraParameters(['extra.key' => 'extra_value']);

        $this->assertEquals('extra_value', $app->getParameter('extra.key'));
    }

    public function testGetParameterReturnsDefaultWhenNotFound()
    {
        $app = new MicroKernel([], true);

        $this->assertEquals('default_val', $app->getParameter('nonexistent', 'default_val'));
    }

    public function testGetParameterReturnsNullByDefault()
    {
        $app = new MicroKernel([], true);

        $this->assertNull($app->getParameter('nonexistent'));
    }

    // ---------------------------------------------------------------
    // getToken()
    // ---------------------------------------------------------------

    public function testGetTokenReturnsNullWhenNoTokenStorage()
    {
        $app = new MicroKernel([], true);

        $this->assertNull($app->getToken());
    }

    public function testGetTokenReturnsTokenFromValidStorage()
    {
        $app = new MicroKernel([], true);

        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage->method('getToken')->willReturn($token);

        $app->setTokenStorage($tokenStorage);

        $this->assertSame($token, $app->getToken());
    }

    // ---------------------------------------------------------------
    // getUser()
    // ---------------------------------------------------------------

    public function testGetUserReturnsNullWhenNoToken()
    {
        $app = new MicroKernel([], true);

        $this->assertNull($app->getUser());
    }

    public function testGetUserReturnsUserFromToken()
    {
        $app = new MicroKernel([], true);

        $user = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage->method('getToken')->willReturn($token);

        $app->setTokenStorage($tokenStorage);

        $this->assertSame($user, $app->getUser());
    }

    // ---------------------------------------------------------------
    // getTwig()
    // ---------------------------------------------------------------

    public function testGetTwigReturnsNullWhenNoTwigRegistered()
    {
        $app = new MicroKernel([], true);

        $this->assertNull($app->getTwig());
    }

    public function testGetTwigReturnsTwigEnvironmentWhenRegistered()
    {
        $app = new MicroKernel(
            [
                'twig' => [
                    'template_dir' => __DIR__ . '/Integration/templates',
                ],
            ],
            true
        );
        $app->boot();

        $twig = $app->getTwig();
        $this->assertInstanceOf(\Twig\Environment::class, $twig);
    }
}
