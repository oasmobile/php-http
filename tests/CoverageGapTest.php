<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test;

use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Route;

/**
 * Tests targeting specific uncovered lines in MicroKernel to reach 95% line coverage.
 */
class CoverageGapTest extends TestCase
{
    /** @var mixed */
    private $previousExceptionHandler = null;

    /** @var MicroKernel[] */
    private array $kernels = [];

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();
    }

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
        $this->kernels = [];

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
        $this->previousExceptionHandler = null;
    }

    private function track(MicroKernel $kernel): MicroKernel
    {
        $this->kernels[] = $kernel;
        return $kernel;
    }

    // ─── Bootstrap Config edge cases ─────────────────────────────────

    public function testMiddlewaresConfigAcceptsSingleMiddleware(): void
    {
        $mw = new \Oasis\Mlib\Http\Middlewares\CallbackMiddleware(
            beforeCallback: function (Request $r, MicroKernel $k) { return null; },
            afterCallback: null,
            beforePriority: 0,
            afterPriority: false,
            masterRequestOnly: true,
            kernel: $this->createStub(MicroKernel::class),
        );

        $kernel = $this->track(new MicroKernel(['middlewares' => $mw], true));
        $this->assertInstanceOf(MicroKernel::class, $kernel);
    }

    public function testInjectedArgsConfigAcceptsSingleObject(): void
    {
        $obj = new \stdClass();
        $kernel = $this->track(new MicroKernel(['injected_args' => $obj], true));
        $this->assertInstanceOf(MicroKernel::class, $kernel);
    }

    public function testProvidersConfigAcceptsSingleCompilerPass(): void
    {
        $pass = new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void {}
        };

        $kernel = $this->track(new MicroKernel(['providers' => $pass], true));
        $this->assertInstanceOf(MicroKernel::class, $kernel);
    }

    public function testProvidersConfigRejectsInvalidType(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->track(new MicroKernel(['providers' => [new \stdClass()]], true));
    }

    // ─── Convenience method LogicException branches ──────────────────

    public function testRenderThrowsWithoutTwig(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $this->expectException(\LogicException::class);
        $kernel->render('test.twig');
    }

    public function testRenderWithStreamedResponse(): void
    {
        $kernel = $this->track(new MicroKernel([
            'twig' => ['template_dir' => __DIR__ . '/Twig/templates'],
        ], true));

        $kernel->addRoute('dummy', new Route('/dummy', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $kernel->handle(Request::create('/dummy'));

        $response = $kernel->render('hello.twig', ['name' => 'World'], new StreamedResponse());
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $this->assertStringContainsString('World', ob_get_clean());
    }

    public function testRenderViewThrowsWithoutTwig(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $this->expectException(\LogicException::class);
        $kernel->renderView('test.twig');
    }

    public function testPathThrowsWithoutRouting(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $this->expectException(\LogicException::class);
        $kernel->path('some_route');
    }

    public function testUrlThrowsWithoutRouting(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $this->expectException(\LogicException::class);
        $kernel->url('some_route');
    }

    // ─── error() boot-after + handler skip ───────────────────────────

    public function testErrorAfterBootRegistersDirectly(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () { throw new \RuntimeException('post-boot'); },
        ]));

        $kernel->handle(Request::create('/throw')); // boot

        $kernel->error(function (\Throwable $e, Request $request, int $code) {
            return new Response('post-boot-handled', $code);
        });

        $response = $kernel->handle(Request::create('/throw'));
        $this->assertSame('post-boot-handled', $response->getContent());
    }

    public function testErrorHandlerSkipsWhenResponseAlreadySet(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $order = [];

        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () { throw new \RuntimeException('test'); },
        ]));

        $kernel->error(function (\Throwable $e, Request $r, int $code) use (&$order) {
            $order[] = 'first';
            return new Response('first-wins', $code);
        });
        $kernel->error(function (\Throwable $e, Request $r, int $code) use (&$order) {
            $order[] = 'second';
            return new Response('second', $code);
        });

        $response = $kernel->handle(Request::create('/throw'));
        $this->assertSame('first-wins', $response->getContent());
        $this->assertSame(['first'], $order);
    }

    // ─── Extension registration ──────────────────────────────────────

    public function testProviderExtensionIsRegistered(): void
    {
        $extension = new class extends Extension {
            public function getAlias(): string { return 'test_ext'; }
            public function load(array $configs, ContainerBuilder $container): void {}
        };

        $kernel = $this->track(new MicroKernel(['providers' => [$extension]], true));
        $kernel->addRoute('ext', new Route('/ext', [
            '_controller' => function () { return new Response('ext-ok'); },
        ]));

        $response = $kernel->handle(Request::create('/ext'));
        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── shouldRunErrorHandler branches ──────────────────────────────

    public function testErrorHandlerWithArrayCallable(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $handler = new class {
            public function handle(\RuntimeException $e, Request $r, int $code): Response {
                return new Response('array-handler', $code);
            }
        };
        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () { throw new \RuntimeException('test'); },
        ]));
        $kernel->error([$handler, 'handle']);

        $this->assertSame('array-handler', $kernel->handle(Request::create('/throw'))->getContent());
    }

    public function testErrorHandlerWithInvokableObject(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $handler = new class {
            public function __invoke(\RuntimeException $e, Request $r, int $code): Response {
                return new Response('invokable', $code);
            }
        };
        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () { throw new \RuntimeException('test'); },
        ]));
        $kernel->error($handler);

        $this->assertSame('invokable', $kernel->handle(Request::create('/throw'))->getContent());
    }

    public function testErrorHandlerInvokableSkipsWrongType(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $narrow = new class {
            public function __invoke(\InvalidArgumentException $e, Request $r, int $code): Response {
                return new Response('narrow', $code);
            }
        };
        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () { throw new \RuntimeException('wrong'); },
        ]));
        $kernel->error($narrow);
        $kernel->error(function (\Throwable $e, Request $r, int $code) {
            return new Response('fallback', $code);
        });

        $this->assertSame('fallback', $kernel->handle(Request::create('/throw'))->getContent());
    }

    public function testErrorHandlerWithStringCallable(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () { throw new \RuntimeException('test'); },
        ]));
        $kernel->error('Oasis\\Mlib\\Http\\Test\\Helpers\\stringErrorHandler');

        $this->assertSame('string-handled', $kernel->handle(Request::create('/throw'))->getContent());
    }

    public function testErrorHandlerWithNoParameters(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () { throw new \RuntimeException('test'); },
        ]));
        $kernel->error(function () { return new Response('no-param', 500); });

        $this->assertSame('no-param', $kernel->handle(Request::create('/throw'))->getContent());
    }

    // ─── run() method ────────────────────────────────────────────────

    public function testRunWithExplicitRequest(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('run', new Route('/run', [
            '_controller' => function () { return new Response('run-ok'); },
        ]));

        ob_start();
        $kernel->run(Request::create('/run'));
        $this->assertStringContainsString('run-ok', ob_get_clean());
    }

    public function testRunWithNullRequestUsesGlobals(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('global', new Route('/global', [
            '_controller' => function () { return new Response('global-ok'); },
        ]));

        $_SERVER['REQUEST_URI'] = '/global';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';

        ob_start();
        $kernel->run();
        $this->assertStringContainsString('global-ok', ob_get_clean());
    }

    // ─── Error handler non-Response through view chain ───────────────

    public function testErrorHandlerNonResponseThroughViewChain(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () { throw new \RuntimeException('chain'); },
        ]));

        $kernel->error(function (\Throwable $e, Request $r, int $code) {
            return ['error' => $e->getMessage()];
        });
        $kernel->view(function (mixed $result, Request $r) {
            if (is_array($result)) {
                return new \Symfony\Component\HttpFoundation\JsonResponse($result);
            }
            return null;
        });

        $response = $kernel->handle(Request::create('/throw'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('chain', json_decode($response->getContent(), true)['error']);
    }

    // ─── CloudFront trusted proxies ──────────────────────────────────

    public function testCloudfrontWithValidCache(): void
    {
        $tempDir = sys_get_temp_dir() . '/oasis-cov-cf-' . getmypid();
        @mkdir($tempDir, 0777, true);

        file_put_contents($tempDir . '/aws.ips', json_encode([
            'prefixes' => [
                ['ip_prefix' => '10.0.0.0/8', 'service' => 'CLOUDFRONT'],
                ['ip_prefix' => '172.16.0.0/12', 'service' => 'EC2'],
            ],
            'expire_at' => time() + 86400,
        ], JSON_PRETTY_PRINT));

        $saved = Request::getTrustedProxies();

        $kernel = $this->track(new MicroKernel([
            'cache_dir' => $tempDir,
            'trust_cloudfront_ips' => true,
        ], true));
        $kernel->addRoute('t', new Route('/t', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $kernel->handle(Request::create('/t'));

        $this->assertContains('10.0.0.0/8', Request::getTrustedProxies());
        $this->assertNotContains('172.16.0.0/12', Request::getTrustedProxies());

        Request::setTrustedProxies($saved, Request::getTrustedHeaderSet());
        @unlink($tempDir . '/aws.ips');
        @rmdir($tempDir);
    }

    public function testCloudfrontWithCorruptCache(): void
    {
        $tempDir = sys_get_temp_dir() . '/oasis-cov-cf-corrupt-' . getmypid();
        @mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/aws.ips', 'invalid-json{{{');

        $saved = Request::getTrustedProxies();

        $kernel = $this->track(new MicroKernel([
            'cache_dir' => $tempDir,
            'trust_cloudfront_ips' => true,
        ], true));
        $kernel->addRoute('t', new Route('/t', [
            '_controller' => function () { return new Response('ok'); },
        ]));

        // Should not throw
        $kernel->handle(Request::create('/t'));
        $this->assertTrue(true);

        Request::setTrustedProxies($saved, Request::getTrustedHeaderSet());
        @unlink($tempDir . '/aws.ips');
        @rmdir($tempDir);
    }

    public function testRenderWithExistingResponse(): void
    {
        $kernel = $this->track(new MicroKernel([
            'twig' => ['template_dir' => __DIR__ . '/Twig/templates'],
        ], true));
        $kernel->addRoute('d', new Route('/d', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $kernel->handle(Request::create('/d'));

        $existing = new Response('', 201, ['X-Custom' => 'yes']);
        $response = $kernel->render('hello.twig', ['name' => 'Test'], $existing);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringContainsString('Test', $response->getContent());
    }

    // ─── Slow request handler ────────────────────────────────────────

    public function testSlowRequestCustomHandler(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('slow', new Route('/slow', [
            '_controller' => function () { return new Response('ok'); },
        ]));

        $ref = new \ReflectionProperty($kernel, 'slowRequestThreshold');
        $ref->setValue($kernel, 0);

        $handlerCalled = false;
        $handlerRef = new \ReflectionProperty($kernel, 'slowRequestHandler');
        $handlerRef->setValue($kernel, function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        ob_start();
        $kernel->run(Request::create('/slow'));
        ob_get_clean();

        $this->assertTrue($handlerCalled);
    }

    public function testSlowRequestDefaultHandler(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('slow', new Route('/slow', [
            '_controller' => function () { return new Response('ok'); },
        ]));

        $ref = new \ReflectionProperty($kernel, 'slowRequestThreshold');
        $ref->setValue($kernel, 0);

        ob_start();
        $kernel->run(Request::create('/slow'));
        ob_get_clean();

        $this->assertTrue(true);
    }

    // ─── AbstractSimplePreAuthenticator (deprecated) ─────────────────

    public function testAbstractSimplePreAuthenticatorCreateTokenThrows(): void
    {
        $auth = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
            public function getCredentialsFromRequest(Request $request): mixed { return null; }
        };

        $this->expectException(\LogicException::class);
        $auth->createToken(Request::create('/'), 'main');
    }

    public function testAbstractSimplePreAuthenticatorAuthenticateTokenThrows(): void
    {
        $auth = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
            public function getCredentialsFromRequest(Request $request): mixed { return null; }
        };

        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $provider = $this->createStub(\Symfony\Component\Security\Core\User\UserProviderInterface::class);

        $this->expectException(\LogicException::class);
        $auth->authenticateToken($token, $provider, 'main');
    }

    public function testAbstractSimplePreAuthenticatorSupportsTokenThrows(): void
    {
        $auth = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
            public function getCredentialsFromRequest(Request $request): mixed { return null; }
        };

        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);

        $this->expectException(\LogicException::class);
        $auth->supportsToken($token, 'main');
    }

    // ─── FallbackViewHandler default resolver ────────────────────────

    public function testFallbackViewHandlerUsesDefaultResolver(): void
    {
        $kernel = $this->track(new MicroKernel([], true));

        // Construct without explicit resolver → line 27 covered
        $handler = new \Oasis\Mlib\Http\Views\FallbackViewHandler($kernel);
        $this->assertInstanceOf(\Oasis\Mlib\Http\Views\FallbackViewHandler::class, $handler);
    }

    // ─── ChainedParameterBagDataProvider ─────────────────────────────

    public function testChainedParameterBagGetOptionalReturnsScalar(): void
    {
        $bag = new \Symfony\Component\HttpFoundation\ParameterBag(['key' => 'value']);
        $provider = new \Oasis\Mlib\Http\ChainedParameterBagDataProvider($bag);
        $this->assertSame('value', $provider->getOptional('key'));
    }
}

