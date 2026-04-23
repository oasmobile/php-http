<?php

namespace Oasis\Mlib\Http\Test\ErrorHandlers;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class WrappedExceptionInfoTest extends TestCase
{
    //----------------------------------------------------------------------
    // Construction
    //----------------------------------------------------------------------

    public function testConstructWithNormalCode()
    {
        $exception = new \RuntimeException('test error');
        $info      = new WrappedExceptionInfo($exception, 404);

        $this->assertSame(404, $info->getCode());
        $this->assertSame(404, $info->getOriginalCode());
        $this->assertSame($exception, $info->getException());
    }

    public function testConstructWithCodeZeroConvertsTo500()
    {
        $exception = new \RuntimeException('test error');
        $info      = new WrappedExceptionInfo($exception, 0);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $info->getCode());
        $this->assertSame(0, $info->getOriginalCode());
    }

    //----------------------------------------------------------------------
    // toArray — normal mode
    //----------------------------------------------------------------------

    public function testToArrayNormalMode()
    {
        $exception = new \RuntimeException('something went wrong', 42);
        $info      = new WrappedExceptionInfo($exception, 500);

        $array = $info->toArray();

        $this->assertSame(500, $array['code']);
        $this->assertArrayHasKey('exception', $array);
        $this->assertArrayHasKey('extra', $array);

        $exData = $array['exception'];
        $this->assertSame('RuntimeException', $exData['type']);
        $this->assertSame('something went wrong', $exData['message']);
        $this->assertArrayHasKey('file', $exData);
        $this->assertArrayHasKey('line', $exData);

        // normal mode should NOT include trace
        $this->assertArrayNotHasKey('trace', $array);
    }

    //----------------------------------------------------------------------
    // toArray — rich mode
    //----------------------------------------------------------------------

    public function testToArrayRichModeIncludesTrace()
    {
        $exception = new \RuntimeException('rich error');
        $info      = new WrappedExceptionInfo($exception, 500);

        $array = $info->toArray(true);

        $this->assertArrayHasKey('trace', $array);
        $this->assertSame($exception->getTrace(), $array['trace']);
    }

    //----------------------------------------------------------------------
    // jsonSerialize
    //----------------------------------------------------------------------

    public function testJsonSerializeReturnsSameAsToArray()
    {
        $exception = new \RuntimeException('json test');
        $info      = new WrappedExceptionInfo($exception, 403);

        $this->assertSame($info->toArray(), $info->jsonSerialize());
    }

    //----------------------------------------------------------------------
    // getAttribute / setAttribute
    //----------------------------------------------------------------------

    public function testGetAttributeReturnsNullWhenUnset()
    {
        $info = new WrappedExceptionInfo(new \RuntimeException('test'), 500);

        $this->assertNull($info->getAttribute('nonexistent'));
    }

    public function testSetAndGetAttribute()
    {
        $info = new WrappedExceptionInfo(new \RuntimeException('test'), 500);
        $info->setAttribute('key', 'email');

        $this->assertSame('email', $info->getAttribute('key'));
    }

    //----------------------------------------------------------------------
    // getAttributes
    //----------------------------------------------------------------------

    public function testGetAttributesReturnsFullArray()
    {
        $info = new WrappedExceptionInfo(new \RuntimeException('test'), 500);
        $this->assertSame([], $info->getAttributes());

        $info->setAttribute('a', 1);
        $info->setAttribute('b', 2);

        $this->assertSame(['a' => 1, 'b' => 2], $info->getAttributes());
    }

    //----------------------------------------------------------------------
    // getCode / setCode
    //----------------------------------------------------------------------

    public function testSetCodeModifiesCode()
    {
        $info = new WrappedExceptionInfo(new \RuntimeException('test'), 500);
        $info->setCode(422);

        $this->assertSame(422, $info->getCode());
    }

    //----------------------------------------------------------------------
    // getOriginalCode — immutable after setCode
    //----------------------------------------------------------------------

    public function testGetOriginalCodeRemainsAfterSetCode()
    {
        $info = new WrappedExceptionInfo(new \RuntimeException('test'), 404);
        $info->setCode(500);

        $this->assertSame(404, $info->getOriginalCode());
        $this->assertSame(500, $info->getCode());
    }

    public function testGetOriginalCodePreservesZero()
    {
        $info = new WrappedExceptionInfo(new \RuntimeException('test'), 0);

        // code was converted to 500, but originalCode stays 0
        $this->assertSame(0, $info->getOriginalCode());
        $this->assertSame(500, $info->getCode());
    }

    //----------------------------------------------------------------------
    // getShortExceptionType
    //----------------------------------------------------------------------

    public function testGetShortExceptionType()
    {
        $info = new WrappedExceptionInfo(new \InvalidArgumentException('bad'), 400);

        $this->assertSame('InvalidArgumentException', $info->getShortExceptionType());
    }

    public function testGetShortExceptionTypeWithNamespacedException()
    {
        // Use a namespaced exception to verify short name extraction
        $exception = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('not found');
        $info      = new WrappedExceptionInfo($exception, 404);

        $this->assertSame('NotFoundHttpException', $info->getShortExceptionType());
    }

    //----------------------------------------------------------------------
    // serializeException — nested previous chain
    //----------------------------------------------------------------------

    public function testSerializeExceptionWithPreviousChain()
    {
        $root   = new \LogicException('root cause', 10);
        $middle = new \RuntimeException('middle', 0, $root);
        $top    = new \InvalidArgumentException('top level', 0, $middle);

        $info  = new WrappedExceptionInfo($top, 500);
        $array = $info->toArray();

        $exData = $array['exception'];
        $this->assertSame('InvalidArgumentException', $exData['type']);
        $this->assertSame('top level', $exData['message']);

        // middle — code is 0, so 'code' key should be absent
        $this->assertArrayHasKey('previous', $exData);
        $prev1 = $exData['previous'];
        $this->assertSame('RuntimeException', $prev1['type']);
        $this->assertSame('middle', $prev1['message']);
        $this->assertArrayNotHasKey('code', $prev1);

        // root — code is 10, so 'code' key should be present
        $this->assertArrayHasKey('previous', $prev1);
        $prev2 = $prev1['previous'];
        $this->assertSame('LogicException', $prev2['type']);
        $this->assertSame('root cause', $prev2['message']);
        $this->assertSame(10, $prev2['code']);

        // no further previous
        $this->assertArrayNotHasKey('previous', $prev2);
    }

    //----------------------------------------------------------------------
    // serializeException — exception code 0 vs non-zero
    //----------------------------------------------------------------------

    public function testSerializeExceptionOmitsCodeWhenZero()
    {
        $exception = new \RuntimeException('zero code', 0);
        $info      = new WrappedExceptionInfo($exception, 500);
        $array     = $info->toArray();

        $this->assertArrayNotHasKey('code', $array['exception']);
    }

    public function testSerializeExceptionIncludesCodeWhenNonZero()
    {
        $exception = new \RuntimeException('has code', 42);
        $info      = new WrappedExceptionInfo($exception, 500);
        $array     = $info->toArray();

        $this->assertArrayHasKey('code', $array['exception']);
        $this->assertSame(42, $array['exception']['code']);
    }

    //----------------------------------------------------------------------
    // toArray extra reflects attributes
    //----------------------------------------------------------------------

    public function testToArrayExtraReflectsAttributes()
    {
        $info = new WrappedExceptionInfo(new \RuntimeException('test'), 400);
        $info->setAttribute('key', 'username');

        $array = $info->toArray();
        $this->assertSame(['key' => 'username'], $array['extra']);
    }
}
