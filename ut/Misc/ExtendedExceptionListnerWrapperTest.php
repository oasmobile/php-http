<?php

namespace Oasis\Mlib\Http\Test\Misc;

use Oasis\Mlib\Http\ExtendedExceptionListnerWrapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test subclass that exposes the protected ensureResponse() method for testing.
 */
class TestableExceptionListnerWrapper extends ExtendedExceptionListnerWrapper
{
    public function callEnsureResponse($response, ExceptionEvent $event): void
    {
        $this->ensureResponse($response, $event);
    }
}

class ExtendedExceptionListnerWrapperTest extends TestCase
{
    //----------------------------------------------------------------------
    // ensureResponse — response null + event has no response → do nothing
    //----------------------------------------------------------------------

    public function testEnsureResponseDoesNothingWhenBothNull()
    {
        $wrapper = new TestableExceptionListnerWrapper();
        $event   = $this->createExceptionEvent();

        // Ensure event has no response initially
        $this->assertNull($event->getResponse());

        // Call with null response — should not set any response on the event
        $wrapper->callEnsureResponse(null, $event);

        $this->assertNull($event->getResponse());
    }

    //----------------------------------------------------------------------
    // ensureResponse — response is a Response object → sets event response
    //----------------------------------------------------------------------

    public function testEnsureResponseWithResponseObjectSetsEventResponse()
    {
        $wrapper  = new TestableExceptionListnerWrapper();
        $event    = $this->createExceptionEvent();
        $response = new Response('OK', 200);

        $wrapper->callEnsureResponse($response, $event);

        $this->assertSame($response, $event->getResponse());
    }

    //----------------------------------------------------------------------
    // ensureResponse — response null but event already has response → delegates (no-op for null)
    //----------------------------------------------------------------------

    public function testEnsureResponseDelegatesToParentWhenEventHasResponse()
    {
        $wrapper  = new TestableExceptionListnerWrapper();
        $event    = $this->createExceptionEvent();

        // Pre-set a response on the event
        $existingResponse = new Response('Existing', 200);
        $event->setResponse($existingResponse);

        // response param is null, but event already has a response → should NOT early-return
        // (the condition is: null response AND null event response → early return)
        // Since event has a response, the code falls through to the Response instanceof check,
        // which is false for null, so nothing changes — but the key point is no early return.
        $wrapper->callEnsureResponse(null, $event);

        // The event should still have the existing response
        $this->assertSame($existingResponse, $event->getResponse());
    }

    //----------------------------------------------------------------------
    // ensureResponse — non-null non-Response value + event has no response
    //----------------------------------------------------------------------

    public function testEnsureResponseWithNonNullNonResponseDoesNotSetResponse()
    {
        $wrapper = new TestableExceptionListnerWrapper();
        $event   = $this->createExceptionEvent();

        // A non-null, non-Response value: the condition (null && null) is false,
        // so it falls through. But since 'some string' is not instanceof Response,
        // no response is set on the event.
        $wrapper->callEnsureResponse('some string', $event);

        $this->assertNull($event->getResponse());
    }

    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------

    /**
     * @return ExceptionEvent
     */
    private function createExceptionEvent(): ExceptionEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');

        return new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('test exception')
        );
    }
}
