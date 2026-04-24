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
    /**
     * @param mixed       $result
     * @param MicroKernel $kernel
     *
     * @return Response
     */
    public function renderOnSuccess($result, MicroKernel $kernel);
    
    /**
     * @param WrappedExceptionInfo $exceptionInfo
     * @param MicroKernel          $kernel
     *
     * @return Response
     */
    public function renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel);
}
