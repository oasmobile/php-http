<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test;

use Oasis\Mlib\Http\MicroKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Route;

/**
 * Tests for MicroKernel layer-2 convenience methods: view, abort, redirect, json, stream, sendFile.
 */
class Layer2ConvenienceMethodsTest extends TestCase
{
    // ─── view() ──────────────────────────────────────────────────────

    public function testViewHandlerConvertsNonResponseToResponse(): void
    {
        $kernel = new MicroKernel([], true);

        $kernel->addRoute('data', new Route('/data', [
            '_controller' => function () {
                return ['key' => 'value']; // non-Response return
            },
        ]));

        $kernel->view(function (mixed $result, Request $request) {
            if (is_array($result)) {
                return new JsonResponse($result);
            }
            return null;
        });

        $response = $kernel->handle(Request::create('/data'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"key":"value"}', $response->getContent());
    }

    public function testViewHandlerCalledInRegistrationOrder(): void
    {
        $kernel = new MicroKernel([], true);

        $kernel->addRoute('data', new Route('/data', [
            '_controller' => function () {
                return 'raw-string';
            },
        ]));

        // First handler: skip strings
        $kernel->view(function (mixed $result) {
            if (is_array($result)) {
                return new JsonResponse($result);
            }
            return null;
        });

        // Second handler: handle strings
        $kernel->view(function (mixed $result) {
            if (is_string($result)) {
                return new Response('wrapped: ' . $result);
            }
            return null;
        });

        $response = $kernel->handle(Request::create('/data'));

        $this->assertSame('wrapped: raw-string', $response->getContent());
    }

    // ─── abort() ─────────────────────────────────────────────────────

    public function testAbortThrowsHttpException(): void
    {
        $kernel = new MicroKernel([], true);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Not Found');

        $kernel->abort(404, 'Not Found');
    }

    public function testAbortWithHeaders(): void
    {
        $kernel = new MicroKernel([], true);

        try {
            $kernel->abort(503, 'Maintenance', ['Retry-After' => '3600']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertSame('3600', $e->getHeaders()['Retry-After']);
        }
    }

    public function testAbortInControllerProducesErrorResponse(): void
    {
        $kernel = new MicroKernel([], true);

        $kernel->addRoute('protected', new Route('/protected', [
            '_controller' => function () use ($kernel) {
                $kernel->abort(403, 'Forbidden');
            },
        ]));

        $kernel->error(function (\Throwable $e, Request $request, int $code) {
            return new Response($e->getMessage(), $code);
        });

        $response = $kernel->handle(Request::create('/protected'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden', $response->getContent());
    }

    // ─── redirect() ─────────────────────────────────────────────────

    public function testRedirectReturnsRedirectResponse(): void
    {
        $kernel = new MicroKernel([], true);

        $response = $kernel->redirect('/login');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getTargetUrl());
    }

    public function testRedirectWithCustomStatus(): void
    {
        $kernel = new MicroKernel([], true);

        $response = $kernel->redirect('/new-location', 301);

        $this->assertSame(301, $response->getStatusCode());
    }

    // ─── json() ──────────────────────────────────────────────────────

    public function testJsonReturnsJsonResponse(): void
    {
        $kernel = new MicroKernel([], true);

        $response = $kernel->json(['status' => 'ok']);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"status":"ok"}', $response->getContent());
    }

    public function testJsonWithStatusAndHeaders(): void
    {
        $kernel = new MicroKernel([], true);

        $response = $kernel->json(['error' => 'bad'], 400, ['X-Error' => 'true']);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('true', $response->headers->get('X-Error'));
    }

    // ─── stream() ────────────────────────────────────────────────────

    public function testStreamReturnsStreamedResponse(): void
    {
        $kernel = new MicroKernel([], true);

        $response = $kernel->stream(function () {
            echo 'streamed';
        });

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertSame('streamed', $output);
    }

    public function testStreamWithCustomStatusAndHeaders(): void
    {
        $kernel = new MicroKernel([], true);

        $response = $kernel->stream(function () {
            echo 'data';
        }, 206, ['Content-Type' => 'text/event-stream']);

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
    }

    // ─── sendFile() ──────────────────────────────────────────────────

    public function testSendFileReturnsBinaryFileResponse(): void
    {
        $kernel = new MicroKernel([], true);

        // Use a known file
        $file = __DIR__ . '/../composer.json';
        $response = $kernel->sendFile($file);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSendFileWithContentDisposition(): void
    {
        $kernel = new MicroKernel([], true);

        $file = __DIR__ . '/../composer.json';
        $response = $kernel->sendFile($file, 200, [], 'attachment');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition') ?? ''
        );
    }

    public function testSendFileWithSplFileInfo(): void
    {
        $kernel = new MicroKernel([], true);

        $file = new \SplFileInfo(__DIR__ . '/../composer.json');
        $response = $kernel->sendFile($file, 200, [], 'inline');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString(
            'inline',
            $response->headers->get('Content-Disposition') ?? ''
        );
    }
}
