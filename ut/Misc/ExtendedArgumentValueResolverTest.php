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
    // resolve() returns non-empty — exact match (replaces old supports() test)
    //----------------------------------------------------------------------

    public function testResolveExactMatchReturnsNonEmpty()
    {
        $obj      = new \stdClass();
        $resolver = new ExtendedArgumentValueResolver([$obj]);
        $request  = Request::create('/');
        $argument = $this->createArgumentMetadata('stdClass');

        $results = $this->resolveToArray($resolver, $request, $argument);

        $this->assertNotEmpty($results);
        $this->assertSame($obj, $results[0]);
    }

    //----------------------------------------------------------------------
    // resolve() returns non-empty — instanceof match (replaces old supports() test)
    //----------------------------------------------------------------------

    public function testResolveInstanceofMatchReturnsNonEmpty()
    {
        $obj      = new \RuntimeException('test');
        $resolver = new ExtendedArgumentValueResolver([$obj]);
        $request  = Request::create('/');
        // RuntimeException extends Exception — argument typed as parent class
        $argument = $this->createArgumentMetadata('Exception');

        $results = $this->resolveToArray($resolver, $request, $argument);

        $this->assertNotEmpty($results);
        $this->assertSame($obj, $results[0]);
    }

    //----------------------------------------------------------------------
    // resolve() returns empty — non-existent class (replaces old supports() test)
    //----------------------------------------------------------------------

    public function testResolveNonExistentClassReturnsEmpty()
    {
        $resolver = new ExtendedArgumentValueResolver([new \stdClass()]);
        $request  = Request::create('/');
        $argument = $this->createArgumentMetadata('NonExistent\\ClassName\\That\\Does\\Not\\Exist');

        $results = $this->resolveToArray($resolver, $request, $argument);

        $this->assertEmpty($results);
    }

    //----------------------------------------------------------------------
    // resolve() returns empty — no match (replaces old supports() test)
    //----------------------------------------------------------------------

    public function testResolveNoMatchReturnsEmpty()
    {
        $resolver = new ExtendedArgumentValueResolver([new \stdClass()]);
        $request  = Request::create('/');
        // ArrayObject is a real class but stdClass is not an instance of it
        $argument = $this->createArgumentMetadata('ArrayObject');

        $results = $this->resolveToArray($resolver, $request, $argument);

        $this->assertEmpty($results);
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

        $results = $this->resolveToArray($resolver, $request, $argument);

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

        $results = $this->resolveToArray($resolver, $request, $argument);

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

        $results = $this->resolveToArray($resolver, $request, $argument);

        $this->assertCount(1, $results);
        $this->assertSame($logic, $results[0]);
    }

    //----------------------------------------------------------------------
    // resolve() — null type returns empty
    //----------------------------------------------------------------------

    public function testResolveNullTypeReturnsEmpty()
    {
        $resolver = new ExtendedArgumentValueResolver([new \stdClass()]);
        $request  = Request::create('/');
        $argument = $this->createArgumentMetadata(null);

        $results = $this->resolveToArray($resolver, $request, $argument);

        $this->assertEmpty($results);
    }

    //----------------------------------------------------------------------
    // resolve() — empty resolver returns empty
    //----------------------------------------------------------------------

    public function testResolveEmptyResolverReturnsEmpty()
    {
        $resolver = new ExtendedArgumentValueResolver([]);
        $request  = Request::create('/');
        $argument = $this->createArgumentMetadata('stdClass');

        $results = $this->resolveToArray($resolver, $request, $argument);

        $this->assertEmpty($results);
    }

    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------

    /**
     * @param string|null $type
     *
     * @return ArgumentMetadata
     */
    private function createArgumentMetadata(?string $type): ArgumentMetadata
    {
        $argument = $this->createStub(ArgumentMetadata::class);
        $argument->method('getType')->willReturn($type);

        return $argument;
    }

    /**
     * Convert resolve() iterable result to array for easier assertion.
     */
    private function resolveToArray(ExtendedArgumentValueResolver $resolver, Request $request, ArgumentMetadata $argument): array
    {
        $result = $resolver->resolve($request, $argument);
        return is_array($result) ? $result : iterator_to_array($result);
    }
}
