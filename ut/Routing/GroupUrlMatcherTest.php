<?php

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlMatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

class GroupUrlMatcherTest extends TestCase
{
    //----------------------------------------------------------------------
    // match — first matcher succeeds
    //----------------------------------------------------------------------

    public function testMatchReturnsImmediatelyWhenFirstMatcherSucceeds()
    {
        $context = new RequestContext();

        $matcher1 = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $matcher1->expects($this->once())
                 ->method('match')
                 ->with('/foo')
                 ->willReturn(['_controller' => 'FooController::index']);

        $matcher2 = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $matcher2->expects($this->never())
                 ->method('match');

        $group  = new GroupUrlMatcher($context, [$matcher1, $matcher2]);
        $result = $group->match('/foo');

        $this->assertSame(['_controller' => 'FooController::index'], $result);
    }

    //----------------------------------------------------------------------
    // match — fallback to next matcher
    //----------------------------------------------------------------------

    public function testMatchFallsBackToNextMatcherOnResourceNotFound()
    {
        $context = new RequestContext();

        $matcher1 = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $matcher1->expects($this->once())
                 ->method('match')
                 ->with('/bar')
                 ->willThrowException(new ResourceNotFoundException());

        $matcher2 = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $matcher2->expects($this->once())
                 ->method('match')
                 ->with('/bar')
                 ->willReturn(['_controller' => 'BarController::index']);

        $group  = new GroupUrlMatcher($context, [$matcher1, $matcher2]);
        $result = $group->match('/bar');

        $this->assertSame(['_controller' => 'BarController::index'], $result);
    }

    //----------------------------------------------------------------------
    // match — all matchers fail
    //----------------------------------------------------------------------

    public function testMatchThrowsExceptionWhenAllMatchersFail()
    {
        $context = new RequestContext();

        $matcher1 = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $matcher1->expects($this->once())
                 ->method('match')
                 ->willThrowException(new ResourceNotFoundException('not found 1'));

        $matcher2 = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $matcher2->expects($this->once())
                 ->method('match')
                 ->willThrowException(new ResourceNotFoundException('not found 2'));

        $group = new GroupUrlMatcher($context, [$matcher1, $matcher2]);

        $this->setExpectedException(ResourceNotFoundException::class);
        $group->match('/nonexistent');
    }

    //----------------------------------------------------------------------
    // match — empty matchers array
    //----------------------------------------------------------------------

    public function testMatchThrowsExceptionWhenNoMatchers()
    {
        $context = new RequestContext();
        $group   = new GroupUrlMatcher($context, []);

        $this->setExpectedException(ResourceNotFoundException::class);
        $group->match('/anything');
    }

    //----------------------------------------------------------------------
    // match — single matcher fails, rethrows its exception
    //----------------------------------------------------------------------

    public function testMatchRethrowsLastMatcherException()
    {
        $context = new RequestContext();

        $matcher = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $matcher->expects($this->once())
                ->method('match')
                ->willThrowException(new ResourceNotFoundException('specific message'));

        $group = new GroupUrlMatcher($context, [$matcher]);

        try {
            $group->match('/test');
            $this->fail('Expected ResourceNotFoundException');
        } catch (ResourceNotFoundException $e) {
            $this->assertSame('specific message', $e->getMessage());
        }
    }

    //----------------------------------------------------------------------
    // matchRequest — delegates to match
    //----------------------------------------------------------------------

    public function testMatchRequestDelegatesToMatch()
    {
        $context = new RequestContext();

        $matcher = $this->getMockBuilder(UrlMatcherInterface::class)->getMock();
        $matcher->expects($this->once())
                ->method('match')
                ->with('/hello')
                ->willReturn(['_controller' => 'HelloController::index']);

        $group   = new GroupUrlMatcher($context, [$matcher]);
        $request = Request::create('/hello');
        $result  = $group->matchRequest($request);

        $this->assertSame(['_controller' => 'HelloController::index'], $result);
    }

    //----------------------------------------------------------------------
    // context management — setContext / getContext
    //----------------------------------------------------------------------

    public function testSetContextAndGetContext()
    {
        $context1 = new RequestContext();
        $context2 = new RequestContext('/app');

        $group = new GroupUrlMatcher($context1, []);

        $this->assertSame($context1, $group->getContext());

        $group->setContext($context2);
        $this->assertSame($context2, $group->getContext());
    }
}
