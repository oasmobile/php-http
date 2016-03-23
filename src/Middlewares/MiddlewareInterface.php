<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-07
 * Time: 17:28
 */

namespace Oasis\Mlib\Http\Middlewares;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface MiddlewareInterface
{
    /**
     * returns if this middleware is only for master request
     *
     * @return bool
     */
    public function onlyForMasterRequest();

    public function before(Request $request, Application $application);

    public function after(Request $request, Response $response);

    /**
     * @return integer|false returns priority of middleware in 'before' phase, false means no 'before' phase
     */
    public function getBeforePriority();

    /**
     * @return integer|false returns priority of middleware in 'after' phase, false means no 'after' phase
     */
    public function getAfterPriority();
}
