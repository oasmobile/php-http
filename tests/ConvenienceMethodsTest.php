<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test;

use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

/**
 * Tests for MicroKernel::before(), after(), error() convenience methods.
 */
class ConvenienceMethodsTest extends TestCase
{
    private function buildKernel(): MicroKernel
    {
        $kernel = new MicroKernel([], true);

        // Use programmatic route injection — no YAML needed
        $kernel->addRoute('hello', new Route('/hello', [
            '_controller' => function () {
                return new Response('hello');
            },
        ]));

        return $kernel;
    }

    // ─── before() ────────────────────────────────────────────────────

    public function testBeforeCallbackIsInvoked(): void
    {
        $kernel = $this->buildKernel();
        $invoked = false;

        $kernel->before(function (Request $request, MicroKernel $kernel) use (&$invoked) {
            $invoked = true;
            return null; // do not short-circuit
        });

        $request  = Request::create('/hello');
        $response = $kernel->handle($request);

        $this->assertTrue($invoked);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testBeforeCallbackCanShortCircuit(): void
    {
        $kernel = $this->buildKernel();

        $kernel->before(function (Request $request, MicroKernel $kernel) {
            return new Response('blocked', 403);
        });

        $request  = Request::create('/hello');
        $response = $kernel->handle($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('blocked', $response->getContent());
    }

    public function testBeforeCallbackRespectsHighPriority(): void
    {
        $kernel = $this->buildKernel();
        $order  = [];

        $kernel->before(function () use (&$order) {
            $order[] = 'low';
            return null;
        }, -10);

        $kernel->before(function () use (&$order) {
            $order[] = 'high';
            return null;
        }, 100);

        $kernel->handle(Request::create('/hello'));

        $this->assertSame(['high', 'low'], $order);
    }

    // ─── after() ─────────────────────────────────────────────────────

    public function testAfterCallbackIsInvoked(): void
    {
        $kernel = $this->buildKernel();
        $capturedResponse = null;

        $kernel->after(function (Request $request, Response $response, MicroKernel $kernel) use (&$capturedResponse) {
            $capturedResponse = $response;
        });

        $request  = Request::create('/hello');
        $response = $kernel->handle($request);

        $this->assertSame($response, $capturedResponse);
    }

    public function testAfterCallbackCanModifyResponse(): void
    {
        $kernel = $this->buildKernel();

        $kernel->after(function (Request $request, Response $response) {
            $response->headers->set('X-Custom', 'added');
        });

        $request  = Request::create('/hello');
        $response = $kernel->handle($request);

        $this->assertSame('added', $response->headers->get('X-Custom'));
    }

    // ─── error() ─────────────────────────────────────────────────────

    public function testErrorCallbackHandlesException(): void
    {
        $kernel = new MicroKernel([], true);

        $kernel->addRoute('throw', new Route('/throw', [
            '_controller' => function () {
                throw new \RuntimeException('boom');
            },
        ]));

        $kernel->error(function (\Throwable $e, Request $request, int $code) {
            return new Response('handled: ' . $e->getMessage(), $code);
        });

        $request  = Request::create('/throw');
        $response = $kernel->handle($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('handled: boom', $response->getContent());
    }

    public function testErrorCallbackWithTypeHintFiltering(): void
    {
        $kernel = new MicroKernel([], true);

        $kernel->addRoute('throw-runtime', new Route('/throw-runtime', [
            '_controller' => function () {
                throw new \RuntimeException('not invalid arg');
            },
        ]));

        // This handler only catches \InvalidArgumentException
        $kernel->error(function (\InvalidArgumentException $e, Request $request, int $code) {
            return new Response('invalid-arg', $code);
        });

        // Generic fallback
        $kernel->error(function (\Throwable $e, Request $request, int $code) {
            return new Response('generic', $code);
        });

        $request  = Request::create('/throw-runtime');
        $response = $kernel->handle($request);

        // Should skip the InvalidArgumentException handler and hit the generic one
        $this->assertSame('generic', $response->getContent());
    }

    public function testErrorCallbackReturningNullPassesThrough(): void
    {
        $kernel = new MicroKernel([], true);
        $order  = [];

        $kernel->addRoute('throw2', new Route('/throw2', [
            '_controller' => function () {
                throw new \RuntimeException('test');
            },
        ]));

        $kernel->error(function (\Throwable $e) use (&$order) {
            $order[] = 'first';
            return null; // pass through
        });

        $kernel->error(function (\Throwable $e, Request $request, int $code) use (&$order) {
            $order[] = 'second';
            return new Response('second-handler', $code);
        });

        $request  = Request::create('/throw2');
        $response = $kernel->handle($request);

        $this->assertSame(['first', 'second'], $order);
        $this->assertSame('second-handler', $response->getContent());
    }

    // ─── masterRequestOnly ───────────────────────────────────────────

    public function testBeforeMasterOnlySkipsSubRequest(): void
    {
        $kernel = new MicroKernel([], true);
        $log    = [];

        // This controller dispatches a sub-request internally
        $kernel->addRoute('main', new Route('/main', [
            '_controller' => function () use ($kernel, &$log) {
                $log[] = 'controller:main';
                // Trigger a sub-request
                $subRequest  = Request::create('/sub');
                $subResponse = $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
                return new Response('main+' . $subResponse->getContent());
            },
        ]));

        $kernel->addRoute('sub', new Route('/sub', [
            '_controller' => function () use (&$log) {
                $log[] = 'controller:sub';
                return new Response('sub-ok');
            },
        ]));

        // masterRequestOnly = true (default) → should NOT fire on sub-request
        $kernel->before(function () use (&$log) {
            $log[] = 'before:master-only';
            return null;
        }, 0, true);

        $response = $kernel->handle(Request::create('/main'));

        $this->assertSame('main+sub-ok', $response->getContent());
        // before should fire once (main request), not on sub-request
        $this->assertSame(['before:master-only', 'controller:main', 'controller:sub'], $log);
    }

    public function testBeforeAllRequestsFiresOnSubRequest(): void
    {
        $kernel = new MicroKernel([], true);
        $log    = [];

        $kernel->addRoute('main', new Route('/main', [
            '_controller' => function () use ($kernel, &$log) {
                $log[] = 'controller:main';
                $subRequest  = Request::create('/sub');
                $subResponse = $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
                return new Response('main+' . $subResponse->getContent());
            },
        ]));

        $kernel->addRoute('sub', new Route('/sub', [
            '_controller' => function () use (&$log) {
                $log[] = 'controller:sub';
                return new Response('sub-ok');
            },
        ]));

        // masterRequestOnly = false → should fire on BOTH main and sub-request
        $kernel->before(function () use (&$log) {
            $log[] = 'before:all';
            return null;
        }, 0, false);

        $response = $kernel->handle(Request::create('/main'));

        $this->assertSame('main+sub-ok', $response->getContent());
        // before should fire twice: once for main, once for sub
        $this->assertSame(['before:all', 'controller:main', 'before:all', 'controller:sub'], $log);
    }

    public function testAfterMasterOnlySkipsSubRequest(): void
    {
        $kernel = new MicroKernel([], true);
        $log    = [];

        $kernel->addRoute('main', new Route('/main', [
            '_controller' => function () use ($kernel, &$log) {
                $log[] = 'controller:main';
                $subRequest  = Request::create('/sub');
                $subResponse = $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
                return new Response('main+' . $subResponse->getContent());
            },
        ]));

        $kernel->addRoute('sub', new Route('/sub', [
            '_controller' => function () use (&$log) {
                $log[] = 'controller:sub';
                return new Response('sub-ok');
            },
        ]));

        // masterRequestOnly = true → after fires only for main request
        $kernel->after(function () use (&$log) {
            $log[] = 'after:master-only';
        }, 0, true);

        $kernel->handle(Request::create('/main'));

        // after fires once, after the main response is ready
        $this->assertCount(1, array_filter($log, fn($v) => $v === 'after:master-only'));
    }

    public function testAfterAllRequestsFiresOnSubRequest(): void
    {
        $kernel = new MicroKernel([], true);
        $log    = [];

        $kernel->addRoute('main', new Route('/main', [
            '_controller' => function () use ($kernel, &$log) {
                $log[] = 'controller:main';
                $subRequest  = Request::create('/sub');
                $subResponse = $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
                return new Response('main+' . $subResponse->getContent());
            },
        ]));

        $kernel->addRoute('sub', new Route('/sub', [
            '_controller' => function () use (&$log) {
                $log[] = 'controller:sub';
                return new Response('sub-ok');
            },
        ]));

        // masterRequestOnly = false → after fires on both
        $kernel->after(function () use (&$log) {
            $log[] = 'after:all';
        }, 0, false);

        $kernel->handle(Request::create('/main'));

        // after fires twice: once for sub-response, once for main response
        $this->assertCount(2, array_filter($log, fn($v) => $v === 'after:all'));
    }
}
