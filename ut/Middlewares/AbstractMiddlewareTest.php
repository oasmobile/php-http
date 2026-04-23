<?php

namespace Oasis\Mlib\Http\Test\Middlewares;

use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use PHPUnit\Framework\TestCase;
use Silex\Application;

class AbstractMiddlewareTest extends TestCase
{
    public function testOnlyForMasterRequestDefaultsToTrue()
    {
        $middleware = new TestMiddleware();
        $this->assertTrue($middleware->onlyForMasterRequest());
    }

    public function testGetAfterPriorityDefaultsToLateEvent()
    {
        $middleware = new TestMiddleware();
        $this->assertSame(Application::LATE_EVENT, $middleware->getAfterPriority());
    }

    public function testGetBeforePriorityDefaultsToEarlyEvent()
    {
        $middleware = new TestMiddleware();
        $this->assertSame(Application::EARLY_EVENT, $middleware->getBeforePriority());
    }
}
