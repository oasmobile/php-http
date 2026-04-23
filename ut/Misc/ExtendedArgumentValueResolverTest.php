<?php

namespace Oasis\Mlib\Http\Test\Misc;

use Oasis\Mlib\Http\ExtendedArgumentValueResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ExtendedArgumentValueResolverTest extends TestCase
{
    //----------------------------------------------------------------------
    // Construction
    //----------------------------------------------------------------------

    public function testConstructWithNonObjectThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Auto parameter should be an object.');

        new ExtendedArgumentValueResolver(['not_an_object']);
    }

    public function testConstructWithStringThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtendedArgumentValueResolver(['a string']);
    }

    public function testConstructWithIntegerThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtendedArgumentValueResolver([42]);
    }

    public function testConstructWithObjectsSucceeds()
    {
        $resolver = new ExtendedArgumentValueResolver([new \stdClass()]);
        $this->assertInstanceOf(ExtendedArgumentValueResolver::class, $resolver);
    }

    public function testConstructWithEmptyArraySucceeds()
    {
        $resolver = new ExtendedArgumentValueResolver([]);
        $this->assertInstanceOf(ExtendedArgumentValueResolver::class, $resolver);
    }

    //----------------------------------------------------------------------
    // supports() — exact match
    //----------------------------------------------------------------------

    public function testSupportsExactMatch()
    {
        $obj      = new \stdClass();
        $resolver = new ExtendedArgumentValueResolver([$obj]);
        $request  = Request::create('/');
        $argument = $this->createArgumentMetadata('stdClass');

        $this->assertTrue($resolver->supports($request, $argument));
    }

    //----------------------------------------------------------------------
    // supports() — instanceof match
    //----------------------------------------------------------------------

    public function testSupportsInstanceofMatch()
    {
        $obj      = new \RuntimeException('test');
        $resolver = new ExtendedArgumentValueResolver([$obj]);
        $request  = Request::create('/');
        // RuntimeException extends Exception — argument typed as parent class
        $argument = $this->createArgumentMetadata('Exception');

        $this->assertTrue($resolver->supports($request, $argument));
    }

    //----------------------------------------------------------------------
    // supports() — non-existent class
    //----------------------------------------------------------------------

    public function testSupportsNonExistentClassReturnsFalse()
    {
        $resolver = new ExtendedArgumentValueResolver([new \stdClass()]);
        $request  = Request::create('/');
        $argument = $this->createArgumentMetadata('NonExistent\\ClassName\\That\\Does\\Not\\Exist');

        $this->assertFalse($resolver->supports($request, $argument));
    }

    //----------------------------------------------------------------------
    // supports() — no match
    //----------------------------------------------------------------------

    public function testSupportsNoMatchReturnsFalse()
    {
        $resolver = new ExtendedArgumentValueResolver([new \stdClass()]);
        $request  = Request::create('/');
        // ArrayObject is a real class but stdClass is not an instance of it
        $argument = $this->createArgumentMetadata('ArrayObject');

        $this->assertFalse($resolver->supports($request, $argument));
    }

    //----------------------------------------------------------------------
    // resolve() — exact match
    //----------------------------------------------------------------------

    public function testResolveExactMatch()
    {
        $obj      = new \stdClass();
        $resolver = new ExtendedArgumentValueResolver([$obj]);
        $request  = Request::create('/');
        $argument = $this->createArgumentMetadata('stdClass');

        $results = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $results);
        $this->assertSame($obj, $results[0]);
    }

    //----------------------------------------------------------------------
    // resolve() — instanceof match
    //----------------------------------------------------------------------

    public function testResolveInstanceofMatch()
    {
        $obj      = new \RuntimeException('test');
        $resolver = new ExtendedArgumentValueResolver([$obj]);
        $request  = Request::create('/');
        $argument = $this->createArgumentMetadata('Exception');

        $results = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $results);
        $this->assertSame($obj, $results[0]);
    }

    //----------------------------------------------------------------------
    // resolve() — exact match takes priority over instanceof
    //----------------------------------------------------------------------

    public function testResolveExactMatchTakesPriority()
    {
        $runtime   = new \RuntimeException('runtime');
        $logic     = new \LogicException('logic');
        $resolver  = new ExtendedArgumentValueResolver([$runtime, $logic]);
        $request   = Request::create('/');
        $argument  = $this->createArgumentMetadata('LogicException');

        $results = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $results);
        $this->assertSame($logic, $results[0]);
    }

    //----------------------------------------------------------------------
    // resolve() — multiple instanceof matches yield all
    //----------------------------------------------------------------------

    public function testResolveMultipleInstanceofMatchesYieldAll()
    {
        $runtime = new \RuntimeException('r');
        $logic   = new \LogicException('l');
        $resolver = new ExtendedArgumentValueResolver([$runtime, $logic]);
        $request  = Request::create('/');
        // Both are instances of Exception
        $argument = $this->createArgumentMetadata('Exception');

        $results = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(2, $results);
        $this->assertSame($runtime, $results[0]);
        $this->assertSame($logic, $results[1]);
    }

    //----------------------------------------------------------------------
    // Helper
    //----------------------------------------------------------------------

    /**
     * @param string $type
     *
     * @return ArgumentMetadata|\PHPUnit_Framework_MockObject_MockObject
     */
    private function createArgumentMetadata($type)
    {
        $argument = $this->getMockBuilder(ArgumentMetadata::class)
                         ->disableOriginalConstructor()
                         ->getMock();
        $argument->method('getType')->willReturn($type);

        return $argument;
    }
}
