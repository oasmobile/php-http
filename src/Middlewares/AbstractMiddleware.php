<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-07
 * Time: 17:30
 */

namespace Oasis\Mlib\Http\Middlewares;

use Silex\Application;

abstract class AbstractMiddleware implements MiddlewareInterface
{
    public function onlyForMasterRequest()
    {
        return true;
    }

    public function getAfterPriority()
    {
        return Application::LATE_EVENT;
    }

    public function getBeforePriority()
    {
        return Application::EARLY_EVENT;
    }
}
