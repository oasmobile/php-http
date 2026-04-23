<?php

namespace Oasis\Mlib\Http\Test\Views;

use Oasis\Mlib\Http\Views\JsonViewHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JsonViewHandlerTest extends TestCase
{
    //----------------------------------------------------------------------
    // __invoke — Accept compatible → JsonResponse
    //----------------------------------------------------------------------

    public function testInvokeReturnsJsonResponseWhenAcceptIsJsonCompatible()
    {
        $handler = new JsonViewHandler();
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $result = $handler(['key' => 'value'], $request);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame('{"key":"value"}', $result->getContent());
    }

    public function testInvokeReturnsJsonResponseForTextJson()
    {
        $handler = new JsonViewHandler();
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'text/json');

        $result = $handler(['data' => 1], $request);

        $this->assertInstanceOf(JsonResponse::class, $result);
    }

    //----------------------------------------------------------------------
    // __invoke — Accept not compatible → null
    //----------------------------------------------------------------------

    public function testInvokeReturnsNullWhenAcceptIsNotCompatible()
    {
        $handler = new JsonViewHandler();
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'text/html');

        $result = $handler(['key' => 'value'], $request);

        $this->assertNull($result);
    }

    //----------------------------------------------------------------------
    // wrapResult — scalar/null wrapping
    //----------------------------------------------------------------------

    public function testInvokeWrapsStringScalar()
    {
        $handler = new JsonViewHandler();
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $result = $handler('hello', $request);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $decoded = json_decode($result->getContent(), true);
        $this->assertSame(['result' => 'hello'], $decoded);
    }

    public function testInvokeWrapsIntegerScalar()
    {
        $handler = new JsonViewHandler();
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $result = $handler(42, $request);

        $decoded = json_decode($result->getContent(), true);
        $this->assertSame(['result' => 42], $decoded);
    }

    public function testInvokeWrapsBooleanScalar()
    {
        $handler = new JsonViewHandler();
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $result = $handler(true, $request);

        $decoded = json_decode($result->getContent(), true);
        $this->assertSame(['result' => true], $decoded);
    }

    public function testInvokeWrapsNullValue()
    {
        $handler = new JsonViewHandler();
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $result = $handler(null, $request);

        $decoded = json_decode($result->getContent(), true);
        $this->assertSame(['result' => null], $decoded);
    }

    //----------------------------------------------------------------------
    // wrapResult — array returned directly
    //----------------------------------------------------------------------

    public function testInvokeReturnsArrayDirectly()
    {
        $handler = new JsonViewHandler();
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $data   = ['items' => [1, 2, 3]];
        $result = $handler($data, $request);

        $decoded = json_decode($result->getContent(), true);
        $this->assertSame($data, $decoded);
    }

    //----------------------------------------------------------------------
    // getCompatibleTypes
    //----------------------------------------------------------------------

    public function testGetCompatibleTypesReturnsExpectedTypes()
    {
        $handler = new JsonViewHandler();

        // Use reflection to access protected method
        $reflection = new \ReflectionMethod($handler, 'getCompatibleTypes');
        $reflection->setAccessible(true);
        $types = $reflection->invoke($handler);

        $this->assertSame(['application/json', 'text/json'], $types);
    }
}
