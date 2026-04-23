<?php

use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use PHPUnit\Framework\TestCase;
use Pimple\ServiceProviderInterface;
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
    /** @var int|null saved trusted header set before each test */
    private $savedTrustedHeaderSet;
    /** @var array saved trusted proxies before each test */
    private $savedTrustedProxies;

    protected function setUp()
    {
        parent::setUp();
        $this->savedTrustedProxies   = Request::getTrustedProxies();
        $this->savedTrustedHeaderSet = Request::getTrustedHeaderSet();
    }

    protected function tearDown()
    {
        // Restore global Request state
        Request::setTrustedProxies($this->savedTrustedProxies, $this->savedTrustedHeaderSet);
        parent::tearDown();
    }

    public function testCreationWithOkConfig()
    {
        require __DIR__ . '/app.php';
    }
    
    public function testProductionMode()
    {
        $config = [
        
        ];
        $kernel = new SilexKernel($config, false);
        $kernel['resolver'];
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
        
        new SilexKernel($config, true);
    }
    
    public function testSlowRequest()
    {
        $config                        = [
        ];
        $slowCalled                    = false;
        $app                           = new SilexKernel($config, true);
        $app['slow_request_threshold'] = 300;
        $app['slow_request_handler']   = $app->protect(
            function (Request $request, $start, $sent, $end) use (&$slowCalled) {
                $this->assertEquals('/abc', $request->getPathInfo());
                $this->assertLessThan($end, $start);
                $this->assertLessThan($sent, $start);
                $this->assertLessThan($end, $sent);
                $slowCalled = true;
            }
        );
        $app->get(
            '/abc',
            function () {
                usleep(400000);
                
                return new Response('');
            }
        );
        $app->run(Request::create("/abc"));
        $this->assertTrue($slowCalled);
    }

    // ---------------------------------------------------------------
    // __set() magic properties — trusted_proxies
    // ---------------------------------------------------------------

    public function testSetTrustedProxiesMergesIntoRequestTrustedProxies()
    {
        // Reset to known state
        Request::setTrustedProxies([], Request::getTrustedHeaderSet());

        $app = new SilexKernel([], true);
        $app->trusted_proxies = ['10.0.0.1', '10.0.0.2'];

        $proxies = Request::getTrustedProxies();
        $this->assertContains('10.0.0.1', $proxies);
        $this->assertContains('10.0.0.2', $proxies);
    }

    public function testSetTrustedProxiesNonArrayAutoWrapped()
    {
        Request::setTrustedProxies([], Request::getTrustedHeaderSet());

        $app = new SilexKernel([], true);
        $app->trusted_proxies = '192.168.1.1';

        $proxies = Request::getTrustedProxies();
        $this->assertContains('192.168.1.1', $proxies);
    }

    // ---------------------------------------------------------------
    // __set() magic properties — trusted_header_set
    // ---------------------------------------------------------------

    public function testSetTrustedHeaderSetWithStringConstant()
    {
        $app = new SilexKernel([], true);
        $app->trusted_header_set = 'HEADER_X_FORWARDED_ALL';

        $this->assertEquals(
            Request::HEADER_X_FORWARDED_ALL,
            Request::getTrustedHeaderSet()
        );
    }

    public function testSetTrustedHeaderSetWithIntegerPassThrough()
    {
        $app = new SilexKernel([], true);
        $app->trusted_header_set = Request::HEADER_X_FORWARDED_FOR;

        $this->assertEquals(
            Request::HEADER_X_FORWARDED_FOR,
            Request::getTrustedHeaderSet()
        );
    }

    // ---------------------------------------------------------------
    // __set() magic properties — service_providers
    // ---------------------------------------------------------------

    public function testSetServiceProvidersSingleProvider()
    {
        $app = new SilexKernel([], true);

        $provider = $this->getMockBuilder(ServiceProviderInterface::class)->getMock();
        $provider->expects($this->once())->method('register');

        $app->service_providers = [$provider];
    }

    public function testSetServiceProvidersTupleWithParams()
    {
        $app = new SilexKernel([], true);

        $provider = $this->getMockBuilder(ServiceProviderInterface::class)->getMock();
        $provider->expects($this->once())->method('register');

        $app->service_providers = [[$provider, ['key' => 'value']]];
    }

    public function testSetServiceProvidersInvalidValueThrowsException()
    {
        $app = new SilexKernel([], true);

        $this->setExpectedException(InvalidConfigurationException::class);
        $app->service_providers = ['not_a_provider'];
    }

    // ---------------------------------------------------------------
    // __set() magic properties — middlewares
    // ---------------------------------------------------------------

    public function testSetMiddlewaresValidMiddleware()
    {
        $app = new SilexKernel([], true);
        $middleware = new TestMiddleware();

        // Should not throw
        $app->middlewares = [$middleware];
        $this->assertTrue(true, 'Setting valid middleware should not throw');
    }

    public function testSetMiddlewaresInvalidValueThrowsException()
    {
        $app = new SilexKernel([], true);

        $this->setExpectedException(InvalidConfigurationException::class);
        $app->middlewares = ['not_a_middleware'];
    }

    // ---------------------------------------------------------------
    // __set() magic properties — view_handlers
    // ---------------------------------------------------------------

    public function testSetViewHandlersValidCallable()
    {
        $app = new SilexKernel([], true);

        // Should not throw — JsonViewHandler is callable
        $app->view_handlers = [new JsonViewHandler()];
        $this->assertTrue(true, 'Setting valid view handler should not throw');
    }

    public function testSetViewHandlersInvalidValueThrowsException()
    {
        $app = new SilexKernel([], true);

        $this->setExpectedException(InvalidConfigurationException::class);
        $app->view_handlers = ['not_callable_string_that_does_not_exist_as_function'];
    }

    // ---------------------------------------------------------------
    // __set() magic properties — error_handlers
    // ---------------------------------------------------------------

    public function testSetErrorHandlersValidCallable()
    {
        $app = new SilexKernel([], true);

        $handler = function () { return null; };
        $app->error_handlers = [$handler];
        $this->assertTrue(true, 'Setting valid error handler should not throw');
    }

    public function testSetErrorHandlersInvalidValueThrowsException()
    {
        $app = new SilexKernel([], true);

        $this->setExpectedException(InvalidConfigurationException::class);
        $app->error_handlers = ['not_callable_string_that_does_not_exist_as_function'];
    }

    // ---------------------------------------------------------------
    // __set() magic properties — injected_args
    // ---------------------------------------------------------------

    public function testSetInjectedArgsAddsToControllerInjectedArgs()
    {
        $app = new SilexKernel([], true);
        $handler = new JsonViewHandler();

        $app->injected_args = [$handler];

        // Verify via the resolver_auto_injections service
        $injections = $app['resolver_auto_injections'];
        $this->assertContains($handler, $injections);
    }

    // ---------------------------------------------------------------
    // __set() magic properties — unknown property
    // ---------------------------------------------------------------

    public function testSetUnknownPropertyThrowsLogicException()
    {
        $app = new SilexKernel([], true);

        $this->setExpectedException(\LogicException::class);
        $app->unknown_property = 'value';
    }

    // ---------------------------------------------------------------
    // boot() — conditional registration
    // ---------------------------------------------------------------

    public function testBootWithRoutingConfigRegistersCacheableRouterProvider()
    {
        $app = new SilexKernel(
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

        // After boot with routing config, request_matcher should be available
        $this->assertTrue(isset($app['request_matcher']));
    }

    public function testBootWithTwigConfigRegistersSimpleTwigServiceProvider()
    {
        $app = new SilexKernel(
            [
                'twig' => [
                    'template_dir' => __DIR__ . '/Integration/templates',
                ],
            ],
            true
        );

        $app->boot();

        $this->assertTrue(isset($app['twig']));
        $this->assertInstanceOf(\Twig_Environment::class, $app['twig']);
    }

    public function testBootWithoutOptionalConfigsDoesNotRegisterOptionalProviders()
    {
        $app = new SilexKernel([], true);
        $app->boot();

        // Without routing config, request_matcher should not be GroupUrlMatcher
        // Without twig config, twig should not be set
        $this->assertFalse(isset($app['twig']));
    }

    public function testBootDoubleBootProtection()
    {
        $app = new SilexKernel([], true);
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
        $app = new SilexKernel([], true);

        // No security.authorization_checker registered
        $this->assertFalse($app->isGranted('ROLE_ADMIN'));
    }

    public function testIsGrantedReturnsFalseWhenCheckerIsNotAuthorizationCheckerInterface()
    {
        $app = new SilexKernel([], true);
        $app['security.authorization_checker'] = 'not_a_checker';

        $this->assertFalse($app->isGranted('ROLE_ADMIN'));
    }

    public function testIsGrantedReturnsFalseOnAuthenticationCredentialsNotFoundException()
    {
        $app = new SilexKernel([], true);

        $checker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $checker->method('isGranted')
            ->willThrowException(new AuthenticationCredentialsNotFoundException('No credentials'));

        $app['security.authorization_checker'] = $checker;

        $this->assertFalse($app->isGranted('ROLE_ADMIN'));
    }

    public function testIsGrantedReturnsTrueWhenCheckerGrantsAccess()
    {
        $app = new SilexKernel([], true);

        $checker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $checker->method('isGranted')->willReturn(true);

        $app['security.authorization_checker'] = $checker;

        $this->assertTrue($app->isGranted('ROLE_USER'));
    }

    public function testIsGrantedReturnsFalseWhenCheckerDeniesAccess()
    {
        $app = new SilexKernel([], true);

        $checker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $checker->method('isGranted')->willReturn(false);

        $app['security.authorization_checker'] = $checker;

        $this->assertFalse($app->isGranted('ROLE_ADMIN'));
    }

    // ---------------------------------------------------------------
    // getCacheDirectories()
    // ---------------------------------------------------------------

    public function testGetCacheDirectoriesNoCacheDir()
    {
        $app = new SilexKernel([], true);

        $this->assertEquals([], $app->getCacheDirectories());
    }

    public function testGetCacheDirectoriesWithCacheDir()
    {
        $app = new SilexKernel(['cache_dir' => '/tmp/test-cache'], true);

        $dirs = $app->getCacheDirectories();
        $this->assertContains('/tmp/test-cache', $dirs);
    }

    public function testGetCacheDirectoriesWithRoutingCacheDir()
    {
        $app = new SilexKernel(
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
        $app = new SilexKernel(
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

    public function testGetParameterFromContainer()
    {
        $app = new SilexKernel([], true);
        $app['my.param'] = 'container_value';

        $this->assertEquals('container_value', $app->getParameter('my.param'));
    }

    public function testGetParameterFromExtraParameters()
    {
        $app = new SilexKernel([], true);
        $app->addExtraParameters(['extra.key' => 'extra_value']);

        $this->assertEquals('extra_value', $app->getParameter('extra.key'));
    }

    public function testGetParameterReturnsDefaultWhenNotFound()
    {
        $app = new SilexKernel([], true);

        $this->assertEquals('default_val', $app->getParameter('nonexistent', 'default_val'));
    }

    public function testGetParameterReturnsNullByDefault()
    {
        $app = new SilexKernel([], true);

        $this->assertNull($app->getParameter('nonexistent'));
    }

    // ---------------------------------------------------------------
    // getToken()
    // ---------------------------------------------------------------

    public function testGetTokenReturnsNullWhenNoTokenStorage()
    {
        $app = new SilexKernel([], true);

        $this->assertNull($app->getToken());
    }

    public function testGetTokenReturnsNullWhenTokenStorageIsNotInterface()
    {
        $app = new SilexKernel([], true);
        $app['security.token_storage'] = 'not_a_token_storage';

        $this->assertNull($app->getToken());
    }

    public function testGetTokenReturnsTokenFromValidStorage()
    {
        $app = new SilexKernel([], true);

        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage->method('getToken')->willReturn($token);

        $app['security.token_storage'] = $tokenStorage;

        $this->assertSame($token, $app->getToken());
    }

    // ---------------------------------------------------------------
    // getUser()
    // ---------------------------------------------------------------

    public function testGetUserReturnsNullWhenNoToken()
    {
        $app = new SilexKernel([], true);

        $this->assertNull($app->getUser());
    }

    public function testGetUserReturnsUserFromToken()
    {
        $app = new SilexKernel([], true);

        $user = $this->getMockBuilder(UserInterface::class)->getMock();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage->method('getToken')->willReturn($token);

        $app['security.token_storage'] = $tokenStorage;

        $this->assertSame($user, $app->getUser());
    }

    // ---------------------------------------------------------------
    // getTwig()
    // ---------------------------------------------------------------

    public function testGetTwigReturnsNullWhenNoTwigRegistered()
    {
        $app = new SilexKernel([], true);

        $this->assertNull($app->getTwig());
    }

    public function testGetTwigReturnsTwigEnvironmentWhenRegistered()
    {
        $app = new SilexKernel(
            [
                'twig' => [
                    'template_dir' => __DIR__ . '/Integration/templates',
                ],
            ],
            true
        );
        $app->boot();

        $twig = $app->getTwig();
        $this->assertInstanceOf(\Twig_Environment::class, $twig);
    }
}
