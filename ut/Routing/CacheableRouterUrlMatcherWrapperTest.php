<?php

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterUrlMatcherWrapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

class CacheableRouterUrlMatcherWrapperTest extends TestCase
{
    //----------------------------------------------------------------------
    // match — delegates to inner matcher
    //----------------------------------------------------------------------

    public function testMatchDelegatesToInnerMatcher()
    {
        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->with('/foo')
              ->willReturn([
                  '_controller' => 'some_value',
                  '_route'      => 'foo_route',
              ]);

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, []);
        $result  = $wrapper->match('/foo');

        $this->assertSame('some_value', $result['_controller']);
        $this->assertSame('foo_route', $result['_route']);
    }

    //----------------------------------------------------------------------
    // match — no _controller with :: does not modify
    //----------------------------------------------------------------------

    public function testMatchDoesNotModifyControllerWithoutDoubleColon()
    {
        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->willReturn(['_controller' => 'simple_controller']);

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, ['App\\Controllers']);
        $result  = $wrapper->match('/test');

        $this->assertSame('simple_controller', $result['_controller']);
    }

    //----------------------------------------------------------------------
    // match — class already exists, does not modify _controller
    //----------------------------------------------------------------------

    public function testMatchDoesNotModifyControllerWhenClassAlreadyExists()
    {
        // Use a class that definitely exists in the test environment
        $existingClass = 'PHPUnit\\Framework\\TestCase';
        $controller    = $existingClass . '::someMethod';

        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->willReturn(['_controller' => $controller]);

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, ['Some\\Namespace']);
        $result  = $wrapper->match('/test');

        $this->assertSame($controller, $result['_controller']);
    }

    //----------------------------------------------------------------------
    // match — namespace prefix applied when class does not exist
    //----------------------------------------------------------------------

    public function testMatchPrependsNamespaceWhenClassDoesNotExist()
    {
        // NonExistentController does not exist, but with namespace prefix it should
        // We need a class that exists under a known namespace
        // Use TestCase as the "resolved" class: namespace = 'PHPUnit\Framework', class = 'TestCase'
        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->willReturn(['_controller' => 'TestCase::someMethod']);

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, ['PHPUnit\\Framework']);
        $result  = $wrapper->match('/test');

        $this->assertSame('PHPUnit\\Framework\\TestCase::someMethod', $result['_controller']);
    }

    //----------------------------------------------------------------------
    // match — multiple namespaces, first matching one wins
    //----------------------------------------------------------------------

    public function testMatchUsesFirstMatchingNamespace()
    {
        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->willReturn(['_controller' => 'TestCase::run']);

        // First namespace won't resolve, second will
        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, [
            'NonExistent\\Namespace',
            'PHPUnit\\Framework',
        ]);
        $result = $wrapper->match('/test');

        $this->assertSame('PHPUnit\\Framework\\TestCase::run', $result['_controller']);
    }

    //----------------------------------------------------------------------
    // match — no namespace resolves, controller unchanged
    //----------------------------------------------------------------------

    public function testMatchLeavesControllerUnchangedWhenNoNamespaceResolves()
    {
        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->willReturn(['_controller' => 'TotallyFakeController::action']);

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, [
            'NonExistent\\Ns1',
            'NonExistent\\Ns2',
        ]);
        $result = $wrapper->match('/test');

        $this->assertSame('TotallyFakeController::action', $result['_controller']);
    }

    //----------------------------------------------------------------------
    // match — empty namespaces array, controller unchanged
    //----------------------------------------------------------------------

    public function testMatchLeavesControllerUnchangedWhenNamespacesEmpty()
    {
        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->willReturn(['_controller' => 'FakeController::action']);

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, []);
        $result  = $wrapper->match('/test');

        $this->assertSame('FakeController::action', $result['_controller']);
    }

    //----------------------------------------------------------------------
    // match — namespace with trailing backslash is handled
    //----------------------------------------------------------------------

    public function testMatchHandlesNamespaceWithTrailingBackslash()
    {
        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->willReturn(['_controller' => 'TestCase::someMethod']);

        // Trailing backslash should be trimmed by rtrim in the source
        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, ['PHPUnit\\Framework\\']);
        $result  = $wrapper->match('/test');

        $this->assertSame('PHPUnit\\Framework\\TestCase::someMethod', $result['_controller']);
    }

    //----------------------------------------------------------------------
    // match — non-string _controller is not modified
    //----------------------------------------------------------------------

    public function testMatchDoesNotModifyNonStringController()
    {
        $callable = function () { return 'hello'; };

        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('match')
              ->willReturn(['_controller' => $callable]);

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, ['Some\\Namespace']);
        $result  = $wrapper->match('/test');

        $this->assertSame($callable, $result['_controller']);
    }

    //----------------------------------------------------------------------
    // context — delegates to inner matcher
    //----------------------------------------------------------------------

    public function testSetContextDelegatesToInnerMatcher()
    {
        $context = new RequestContext('/app');

        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('setContext')
              ->with($this->identicalTo($context));

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, []);
        $wrapper->setContext($context);
    }

    public function testGetContextDelegatesToInnerMatcher()
    {
        $context = new RequestContext('/app');

        $inner = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $inner->expects($this->once())
              ->method('getContext')
              ->willReturn($context);

        $wrapper = new CacheableRouterUrlMatcherWrapper($inner, []);
        $this->assertSame($context, $wrapper->getContext());
    }
}
