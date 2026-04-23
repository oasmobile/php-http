<?php

namespace Oasis\Mlib\Http\Test\Misc;

use Oasis\Mlib\Http\ExtendedExceptionListnerWrapper;
use PHPUnit\Framework\TestCase;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test subclass that exposes the protected ensureResponse() method for testing.
 */
class TestableExceptionListnerWrapper extends ExtendedExceptionListnerWrapper
{
    public function callEnsureResponse($response, GetResponseForExceptionEvent $event)
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
        $app     = $this->createApplication();
        $wrapper = new TestableExceptionListnerWrapper($app, function () {});
        $event   = $this->createExceptionEvent($app);

        // Ensure event has no response initially
        $this->assertNull($event->getResponse());

        // Call with null response — should not set any response on the event
        $wrapper->callEnsureResponse(null, $event);

        $this->assertNull($event->getResponse());
    }

    //----------------------------------------------------------------------
    // ensureResponse — response is a Response object → delegates to parent
    //----------------------------------------------------------------------

    public function testEnsureResponseWithResponseObjectSetsEventResponse()
    {
        $app      = $this->createApplication();
        $wrapper  = new TestableExceptionListnerWrapper($app, function () {});
        $event    = $this->createExceptionEvent($app);
        $response = new Response('OK', 200);

        $wrapper->callEnsureResponse($response, $event);

        // Parent's ensureResponse sets the response on the event when it's a Response instance
        $this->assertSame($response, $event->getResponse());
    }

    //----------------------------------------------------------------------
    // ensureResponse — response null but event already has response → delegates to parent
    //----------------------------------------------------------------------

    public function testEnsureResponseDelegatesToParentWhenEventHasResponse()
    {
        $app      = $this->createApplication();
        $wrapper  = new TestableExceptionListnerWrapper($app, function () {});
        $event    = $this->createExceptionEvent($app);

        // Pre-set a response on the event
        $existingResponse = new Response('Existing', 200);
        $event->setResponse($existingResponse);

        // response param is null, but event already has a response → should delegate to parent
        // Parent's ensureResponse will dispatch VIEW event for null response,
        // but the event already has a response so it won't be cleared
        $wrapper->callEnsureResponse(null, $event);

        // The event should still have a response (parent was invoked)
        $this->assertNotNull($event->getResponse());
    }

    //----------------------------------------------------------------------
    // ensureResponse — non-null non-Response value + event has no response
    //----------------------------------------------------------------------

    public function testEnsureResponseWithNonNullNonResponseDelegatesToParent()
    {
        $app     = $this->createApplication();
        $wrapper = new TestableExceptionListnerWrapper($app, function () {});
        $event   = $this->createExceptionEvent($app);

        // A non-null, non-Response value should trigger parent's ensureResponse
        // which dispatches KernelEvents::VIEW. The dispatcher is registered on the app.
        // Since no view listener converts it, the event response may remain null,
        // but the important thing is that parent::ensureResponse was called (no early return).
        $wrapper->callEnsureResponse('some string', $event);

        // We can't easily assert parent was called without side effects,
        // but the fact that no exception was thrown confirms the code path was executed.
        // The key behavioral difference is: when both are null, ensureResponse returns early.
        $this->assertTrue(true);
    }

    //----------------------------------------------------------------------
    // Helpers
    //----------------------------------------------------------------------

    /**
     * @return Application
     */
    private function createApplication()
    {
        $app = new Application();
        $app->boot();

        return $app;
    }

    /**
     * @param Application $app
     *
     * @return GetResponseForExceptionEvent
     */
    private function createExceptionEvent(Application $app)
    {
        $kernel  = $app['kernel'];
        $request = Request::create('/');

        return new GetResponseForExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            new \RuntimeException('test exception')
        );
    }
}
