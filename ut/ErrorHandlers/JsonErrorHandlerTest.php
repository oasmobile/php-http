<?php

namespace Oasis\Mlib\Http\Test\ErrorHandlers;

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class JsonErrorHandlerTest extends TestCase
{
    /** @var JsonErrorHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->handler = new JsonErrorHandler();
    }

    //----------------------------------------------------------------------
    // Return array structure
    //----------------------------------------------------------------------

    public function testInvokeReturnsArrayWithExpectedKeys()
    {
        $exception = new \RuntimeException('test message');
        $result    = call_user_func($this->handler, $exception, Request::create('/'), 500);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('line', $result);
    }

    public function testInvokeReturnsCorrectValues()
    {
        $exception = new \RuntimeException('something broke');
        $result    = call_user_func($this->handler, $exception, Request::create('/'), 503);

        $this->assertSame(503, $result['code']);
        $this->assertSame('something broke', $result['message']);
        $this->assertSame($exception->getFile(), $result['file']);
        $this->assertSame($exception->getLine(), $result['line']);
    }

    //----------------------------------------------------------------------
    // type is full class name
    //----------------------------------------------------------------------

    public function testTypeIsFullClassName()
    {
        $exception = new \RuntimeException('test');
        $result    = call_user_func($this->handler, $exception, Request::create('/'), 500);

        $this->assertSame('RuntimeException', $result['type']);
    }

    public function testTypeIsFullClassNameForNamespacedException()
    {
        $exception = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('not found');
        $result    = call_user_func($this->handler, $exception, Request::create('/'), 404);

        $this->assertSame(
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
            $result['type']
        );
    }

    //----------------------------------------------------------------------
    // Different code values pass through
    //----------------------------------------------------------------------

    public function testDifferentCodeValuesPassThrough()
    {
        $exception = new \RuntimeException('error');

        $codes = [200, 400, 403, 404, 500, 503];
        foreach ($codes as $code) {
            $result = call_user_func($this->handler, $exception, Request::create('/'), $code);
            $this->assertSame($code, $result['code'], "Code $code should pass through");
        }
    }

    public function testCodeZeroPassesThrough()
    {
        $exception = new \RuntimeException('error');
        $result    = call_user_func($this->handler, $exception, Request::create('/'), 0);

        $this->assertSame(0, $result['code']);
    }
}
