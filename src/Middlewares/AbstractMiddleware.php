<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-07
 * Time: 17:30
 */

namespace Oasis\Mlib\Http\Middlewares;

use Oasis\Mlib\Http\MicroKernel;

abstract class AbstractMiddleware implements MiddlewareInterface
{
    public function onlyForMasterRequest(): bool
    {
        return true;
    }

    public function getAfterPriority(): int|false
    {
        return MicroKernel::AFTER_PRIORITY_LATEST;
    }

    public function getBeforePriority(): int|false
    {
        return MicroKernel::BEFORE_PRIORITY_EARLIEST;
    }
}
