<?php

namespace Oasis\Mlib\Http\Test\ErrorHandlers;

use Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper;
use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Utils\Exceptions\DataValidationException;
use Oasis\Mlib\Utils\Exceptions\ExistenceViolationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExceptionWrapperTest extends TestCase
{
    /** @var ExceptionWrapper */
    private $wrapper;

    /** @var Request */
    private $request;

    protected function setUp(): void
    {
        $this->wrapper = new ExceptionWrapper();
        $this->request = Request::create('/test');
    }

    //----------------------------------------------------------------------
    // Basic wrapping
    //----------------------------------------------------------------------

    public function testInvokeReturnsWrappedExceptionInfo()
    {
        $exception = new \RuntimeException('basic error', 0);
        $result    = call_user_func($this->wrapper, $exception, $this->request, 500);

        $this->assertInstanceOf(WrappedExceptionInfo::class, $result);
    }

    //----------------------------------------------------------------------
    // ExistenceViolationException → 404 + key
    //----------------------------------------------------------------------

    public function testExistenceViolationExceptionSetsCodeTo404AndKey()
    {
        $exception = (new ExistenceViolationException('not found'))
            ->withFieldName('user_id');

        $result = call_user_func($this->wrapper, $exception, $this->request, 500);

        $this->assertSame(Response::HTTP_NOT_FOUND, $result->getCode());
        $this->assertSame('user_id', $result->getAttribute('key'));
    }

    //----------------------------------------------------------------------
    // DataValidationException → 400 + key
    //----------------------------------------------------------------------

    public function testDataValidationExceptionSetsCodeTo400AndKey()
    {
        $exception = (new DataValidationException('invalid input'))
            ->withFieldName('email');

        $result = call_user_func($this->wrapper, $exception, $this->request, 500);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $result->getCode());
        $this->assertSame('email', $result->getAttribute('key'));
    }

    //----------------------------------------------------------------------
    // Plain Exception — keeps original httpStatusCode
    //----------------------------------------------------------------------

    public function testPlainExceptionKeepsOriginalCode()
    {
        $exception = new \RuntimeException('generic error');
        $result    = call_user_func($this->wrapper, $exception, $this->request, 503);

        $this->assertSame(503, $result->getCode());
        $this->assertNull($result->getAttribute('key'));
    }

    public function testPlainExceptionWithDifferentCode()
    {
        $exception = new \LogicException('logic error');
        $result    = call_user_func($this->wrapper, $exception, $this->request, 422);

        $this->assertSame(422, $result->getCode());
    }

    //----------------------------------------------------------------------
    // ExistenceViolationException inherits DataValidationException
    // — verify it matches the ExistenceViolationException branch (404),
    //   not the DataValidationException branch (400)
    //----------------------------------------------------------------------

    public function testExistenceViolationTakesPriorityOverDataValidation()
    {
        $exception = (new ExistenceViolationException('missing'))
            ->withFieldName('order_id');

        $result = call_user_func($this->wrapper, $exception, $this->request, 500);

        // ExistenceViolationException extends DataValidationException,
        // but the switch(true) checks ExistenceViolationException first → 404
        $this->assertSame(Response::HTTP_NOT_FOUND, $result->getCode());
    }
}
