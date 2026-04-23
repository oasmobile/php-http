<?php

namespace Oasis\Mlib\Http\Test\Routing;

use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class GroupUrlGeneratorTest extends TestCase
{
    //----------------------------------------------------------------------
    // generate — first generator succeeds
    //----------------------------------------------------------------------

    public function testGenerateReturnsImmediatelyWhenFirstGeneratorSucceeds()
    {
        $gen1 = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen1->expects($this->once())
             ->method('generate')
             ->with('home', [], UrlGeneratorInterface::ABSOLUTE_PATH)
             ->willReturn('/home');

        $gen2 = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen2->expects($this->never())
             ->method('generate');

        $group  = new GroupUrlGenerator([$gen1, $gen2]);
        $result = $group->generate('home');

        $this->assertSame('/home', $result);
    }

    //----------------------------------------------------------------------
    // generate — fallback to next generator
    //----------------------------------------------------------------------

    public function testGenerateFallsBackToNextGeneratorOnRouteNotFound()
    {
        $gen1 = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen1->expects($this->once())
             ->method('generate')
             ->willThrowException(new RouteNotFoundException());

        $gen2 = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen2->expects($this->once())
             ->method('generate')
             ->with('about', ['id' => 1], UrlGeneratorInterface::ABSOLUTE_PATH)
             ->willReturn('/about?id=1');

        $group  = new GroupUrlGenerator([$gen1, $gen2]);
        $result = $group->generate('about', ['id' => 1]);

        $this->assertSame('/about?id=1', $result);
    }

    //----------------------------------------------------------------------
    // generate — all generators fail
    //----------------------------------------------------------------------

    public function testGenerateThrowsExceptionWhenAllGeneratorsFail()
    {
        $gen1 = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen1->expects($this->once())
             ->method('generate')
             ->willThrowException(new RouteNotFoundException('not found 1'));

        $gen2 = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen2->expects($this->once())
             ->method('generate')
             ->willThrowException(new RouteNotFoundException('not found 2'));

        $group = new GroupUrlGenerator([$gen1, $gen2]);

        $this->setExpectedException(RouteNotFoundException::class);
        $group->generate('nonexistent');
    }

    //----------------------------------------------------------------------
    // generate — empty generators array
    //----------------------------------------------------------------------

    public function testGenerateThrowsExceptionWhenNoGenerators()
    {
        $group = new GroupUrlGenerator([]);

        $this->setExpectedException(RouteNotFoundException::class);
        $group->generate('anything');
    }

    //----------------------------------------------------------------------
    // generate — single generator fails, rethrows its exception
    //----------------------------------------------------------------------

    public function testGenerateRethrowsLastGeneratorException()
    {
        $gen = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen->expects($this->once())
            ->method('generate')
            ->willThrowException(new RouteNotFoundException('specific route error'));

        $group = new GroupUrlGenerator([$gen]);

        try {
            $group->generate('missing');
            $this->fail('Expected RouteNotFoundException');
        } catch (RouteNotFoundException $e) {
            $this->assertSame('specific route error', $e->getMessage());
        }
    }

    //----------------------------------------------------------------------
    // generate — context is passed to sub-generators
    //----------------------------------------------------------------------

    public function testGeneratePassesContextToSubGenerators()
    {
        $context = new RequestContext('/app');

        $gen1 = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen1->expects($this->once())
             ->method('setContext')
             ->with($this->identicalTo($context));
        $gen1->expects($this->once())
             ->method('generate')
             ->willReturn('/app/home');

        $group = new GroupUrlGenerator([$gen1]);
        $group->setContext($context);

        $result = $group->generate('home');
        $this->assertSame('/app/home', $result);
    }

    public function testGenerateDoesNotSetContextOnSubGeneratorsWhenContextIsNull()
    {
        $gen1 = $this->getMockBuilder(UrlGeneratorInterface::class)->getMock();
        $gen1->expects($this->never())
             ->method('setContext');
        $gen1->expects($this->once())
             ->method('generate')
             ->willReturn('/home');

        $group = new GroupUrlGenerator([$gen1]);
        // context is null by default

        $result = $group->generate('home');
        $this->assertSame('/home', $result);
    }

    //----------------------------------------------------------------------
    // context management — setContext / getContext
    //----------------------------------------------------------------------

    public function testSetContextAndGetContext()
    {
        $context1 = new RequestContext();
        $context2 = new RequestContext('/app');

        $group = new GroupUrlGenerator([]);

        $this->assertNull($group->getContext());

        $group->setContext($context1);
        $this->assertSame($context1, $group->getContext());

        $group->setContext($context2);
        $this->assertSame($context2, $group->getContext());
    }
}
