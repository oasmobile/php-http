<?php

namespace Oasis\Mlib\Http\Test\Helpers\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Concrete Test_Double for AbstractMiddleware.
 *
 * Records before() / after() call information for test assertions.
 */
class TestMiddleware extends AbstractMiddleware
{
    /** @var array */
    private $beforeCalls = [];

    /** @var array */
    private $afterCalls = [];

    /**
     * {@inheritdoc}
     */
    public function before(Request $request, MicroKernel $kernel)
    {
        $this->beforeCalls[] = [
            'request' => $request,
            'kernel' => $kernel,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function after(Request $request, Response $response)
    {
        $this->afterCalls[] = [
            'request' => $request,
            'response' => $response,
        ];
    }

    /**
     * @return array
     */
    public function getBeforeCalls()
    {
        return $this->beforeCalls;
    }

    /**
     * @return array
     */
    public function getAfterCalls()
    {
        return $this->afterCalls;
    }

    /**
     * Reset recorded calls.
     */
    public function reset()
    {
        $this->beforeCalls = [];
        $this->afterCalls = [];
    }
}
