<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-07
 * Time: 17:28
 */

namespace Oasis\Mlib\Http\Middlewares;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface MiddlewareInterface
{
    /**
     * Returns if this middleware is only for master request.
     */
    public function onlyForMasterRequest(): bool;

    public function before(Request $request, MicroKernel $kernel);

    public function after(Request $request, Response $response);

    /**
     * @return int|false returns priority of middleware in 'before' phase, false means no 'before' phase
     */
    public function getBeforePriority(): int|false;

    /**
     * @return int|false returns priority of middleware in 'after' phase, false means no 'after' phase
     */
    public function getAfterPriority(): int|false;
}
