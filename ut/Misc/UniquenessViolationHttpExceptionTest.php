<?php

namespace Oasis\Mlib\Http\Test\Misc;

use Oasis\Mlib\Http\Exceptions\UniquenessViolationHttpException;
use PHPUnit\Framework\TestCase;

class UniquenessViolationHttpExceptionTest extends TestCase
{
    //----------------------------------------------------------------------
    // getStatusCode() — always 400
    //----------------------------------------------------------------------

    public function testGetStatusCodeReturns400()
    {
        $exception = new UniquenessViolationHttpException('duplicate entry');

        $this->assertSame(400, $exception->getStatusCode());
    }

    //----------------------------------------------------------------------
    // getMessage()
    //----------------------------------------------------------------------

    public function testGetMessageReturnsProvidedMessage()
    {
        $exception = new UniquenessViolationHttpException('duplicate entry');

        $this->assertSame('duplicate entry', $exception->getMessage());
    }

    public function testGetMessageDefaultsToEmptyWhenNull()
    {
        $exception = new UniquenessViolationHttpException();

        // null message becomes empty string in PHP Exception
        $this->assertSame('', $exception->getMessage());
    }

    //----------------------------------------------------------------------
    // getPrevious()
    //----------------------------------------------------------------------

    public function testGetPreviousReturnsProvidedException()
    {
        $previous  = new \RuntimeException('original error');
        $exception = new UniquenessViolationHttpException('duplicate', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testGetPreviousReturnsNullWhenNotProvided()
    {
        $exception = new UniquenessViolationHttpException('duplicate');

        $this->assertNull($exception->getPrevious());
    }

    //----------------------------------------------------------------------
    // HttpException inheritance
    //----------------------------------------------------------------------

    public function testIsInstanceOfHttpException()
    {
        $exception = new UniquenessViolationHttpException('test');

        $this->assertInstanceOf(
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
            $exception
        );
    }
}
