<?php

namespace Oasis\Mlib\Http\Test\Views;

use Oasis\Mlib\Http\Test\Helpers\Views\ConcreteSmartViewHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class AbstractSmartViewHandlerTest extends TestCase
{
    //----------------------------------------------------------------------
    // Compatible type matching
    //----------------------------------------------------------------------

    public function testShouldHandleReturnsTrueWhenAcceptContainsCompatibleType()
    {
        $handler = new ConcreteSmartViewHandler(['application/json', 'text/json']);
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $this->assertTrue($handler->shouldHandle($request));
    }

    public function testShouldHandleReturnsTrueForMultipleAcceptWithOneCompatible()
    {
        $handler = new ConcreteSmartViewHandler(['text/json']);
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'text/html, text/json;q=0.9');

        $this->assertTrue($handler->shouldHandle($request));
    }

    //----------------------------------------------------------------------
    // */* Accept
    //----------------------------------------------------------------------

    public function testShouldHandleReturnsTrueWhenAcceptIsWildcard()
    {
        $handler = new ConcreteSmartViewHandler(['application/json']);
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', '*/*');

        $this->assertTrue($handler->shouldHandle($request));
    }

    //----------------------------------------------------------------------
    // Empty Accept (defaults to */* per source code)
    //----------------------------------------------------------------------

    public function testShouldHandleReturnsTrueWhenAcceptIsEmpty()
    {
        $handler = new ConcreteSmartViewHandler(['application/json']);
        $request = Request::create('/', 'GET');
        $request->headers->remove('Accept');

        $this->assertTrue($handler->shouldHandle($request));
    }

    //----------------------------------------------------------------------
    // Incompatible type
    //----------------------------------------------------------------------

    public function testShouldHandleReturnsFalseWhenAcceptIsIncompatible()
    {
        $handler = new ConcreteSmartViewHandler(['application/json']);
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'text/html');

        $this->assertFalse($handler->shouldHandle($request));
    }

    public function testShouldHandleReturnsFalseWhenNoCompatibleTypes()
    {
        $handler = new ConcreteSmartViewHandler([]);
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $this->assertFalse($handler->shouldHandle($request));
    }

    //----------------------------------------------------------------------
    // Wildcard matching (e.g. application/*)
    //----------------------------------------------------------------------

    public function testShouldHandleReturnsTrueForGroupWildcard()
    {
        $handler = new ConcreteSmartViewHandler(['application/json']);
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/*');

        $this->assertTrue($handler->shouldHandle($request));
    }

    public function testShouldHandleReturnsFalseForGroupWildcardWithDifferentGroup()
    {
        $handler = new ConcreteSmartViewHandler(['application/json']);
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'text/*');

        $this->assertFalse($handler->shouldHandle($request));
    }

    //----------------------------------------------------------------------
    // Case insensitivity
    //----------------------------------------------------------------------

    public function testShouldHandleIsCaseInsensitive()
    {
        $handler = new ConcreteSmartViewHandler(['Application/JSON']);
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/json');

        $this->assertTrue($handler->shouldHandle($request));
    }
}
