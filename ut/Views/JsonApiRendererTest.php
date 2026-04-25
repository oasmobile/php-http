<?php

namespace Oasis\Mlib\Http\Test\Views;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Views\JsonApiRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class JsonApiRendererTest extends TestCase
{
    /**
     * @return MicroKernel
     */
    private function createMinimalKernel()
    {
        return new MicroKernel([], true);
    }

    //----------------------------------------------------------------------
    // renderOnSuccess — array input returned directly as JsonResponse
    //----------------------------------------------------------------------

    public function testRenderOnSuccessWithArrayReturnsJsonResponseDirectly()
    {
        $renderer = new JsonApiRenderer();
        $kernel   = $this->createMinimalKernel();

        $data     = ['items' => [1, 2, 3], 'total' => 3];
        $response = $renderer->renderOnSuccess($data, $kernel);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame($data, $decoded);
    }

    public function testRenderOnSuccessWithEmptyArrayReturnsJsonResponse()
    {
        $renderer = new JsonApiRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess([], $kernel);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame([], $decoded);
    }

    //----------------------------------------------------------------------
    // renderOnSuccess — non-array input wrapped as ["result" => $value]
    //----------------------------------------------------------------------

    public function testRenderOnSuccessWithStringWrapsAsResult()
    {
        $renderer = new JsonApiRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess('hello', $kernel);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(['result' => 'hello'], $decoded);
    }

    public function testRenderOnSuccessWithIntegerWrapsAsResult()
    {
        $renderer = new JsonApiRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess(42, $kernel);

        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(['result' => 42], $decoded);
    }

    public function testRenderOnSuccessWithBooleanWrapsAsResult()
    {
        $renderer = new JsonApiRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess(true, $kernel);

        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(['result' => true], $decoded);
    }

    public function testRenderOnSuccessWithNullWrapsAsResult()
    {
        $renderer = new JsonApiRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess(null, $kernel);

        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(['result' => null], $decoded);
    }

    //----------------------------------------------------------------------
    // renderOnException — status code from exception code
    //----------------------------------------------------------------------

    public function testRenderOnExceptionReturnsJsonResponseWithExceptionCode()
    {
        $renderer      = new JsonApiRenderer();
        $kernel        = $this->createMinimalKernel();
        $exceptionInfo = new WrappedExceptionInfo(new \RuntimeException('not found'), 404);

        $response = $renderer->renderOnException($exceptionInfo, $kernel);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRenderOnExceptionReturnsJsonResponseWith500()
    {
        $renderer      = new JsonApiRenderer();
        $kernel        = $this->createMinimalKernel();
        $exceptionInfo = new WrappedExceptionInfo(new \RuntimeException('server error'), 500);

        $response = $renderer->renderOnException($exceptionInfo, $kernel);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testRenderOnExceptionContentContainsExceptionData()
    {
        $renderer      = new JsonApiRenderer();
        $kernel        = $this->createMinimalKernel();
        $exceptionInfo = new WrappedExceptionInfo(new \RuntimeException('test error'), 400);

        $response = $renderer->renderOnException($exceptionInfo, $kernel);

        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(400, $decoded['code']);
        $this->assertSame('RuntimeException', $decoded['exception']['type']);
        $this->assertSame('test error', $decoded['exception']['message']);
    }
}
