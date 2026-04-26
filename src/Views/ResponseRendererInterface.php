<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 16:42
 */

namespace Oasis\Mlib\Http\Views;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Response;

interface ResponseRendererInterface
{
    public function renderOnSuccess(mixed $result, MicroKernel $kernel): Response;
    
    public function renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel): Response;
}
