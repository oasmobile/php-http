<?php

namespace Oasis\Mlib\Http\Test\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\Middlewares\TestMiddleware;
use PHPUnit\Framework\TestCase;

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
        $this->assertSame(MicroKernel::AFTER_PRIORITY_LATEST, $middleware->getAfterPriority());
    }

    public function testGetBeforePriorityDefaultsToEarlyEvent()
    {
        $middleware = new TestMiddleware();
        $this->assertSame(MicroKernel::BEFORE_PRIORITY_EARLIEST, $middleware->getBeforePriority());
    }
}
