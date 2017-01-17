<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 16:42
 */

namespace Oasis\Mlib\Http\Views;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\SilexKernel;
use Symfony\Component\HttpFoundation\Response;

interface ResponseRendererInterface
{
    /**
     * @param mixed       $result
     * @param SilexKernel $silexKernel
     *
     * @return Response
     */
    public function renderOnSuccess($result, SilexKernel $silexKernel);
    
    /**
     * @param WrappedExceptionInfo $exceptionInfo
     * @param SilexKernel          $silexKernel
     *
     * @return Response
     */
    public function renderOnException(WrappedExceptionInfo $exceptionInfo, SilexKernel $silexKernel);
}
