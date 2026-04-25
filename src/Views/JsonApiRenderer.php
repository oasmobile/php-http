<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 16:48
 */

namespace Oasis\Mlib\Http\Views;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JsonApiRenderer implements ResponseRendererInterface
{
    
    public function renderOnSuccess(mixed $result, MicroKernel $kernel): Response
    {
        if (!is_array($result)) {
            $result = ['result' => $result];
        }
        
        return new JsonResponse($result);
    }
    
    public function renderOnException(WrappedExceptionInfo $exceptionInfo, MicroKernel $kernel): Response
    {
        return new JsonResponse(
            $exceptionInfo,
            $exceptionInfo->getCode()
        );
    }
}
