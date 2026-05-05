<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\KernelLifecycleTestTrait;
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
 * Tests targeting specific uncovered lines in MicroKernel (bootstrap, convenience, error handling).
 */
class CoverageGapTest extends TestCase
{
    use KernelLifecycleTestTrait;

    protected function setUp(): void
    {
        $this->setUpKernelLifecycle();
    }

    protected function tearDown(): void
    {
        $this->tearDownKernelLifecycle();
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
}
