<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-05
 * Time: 14:57
 */

namespace Oasis\Mlib\Http\ErrorHandlers;

use Oasis\Mlib\Utils\Exceptions\DataValidationException;
use Oasis\Mlib\Utils\Exceptions\ExistenceViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExceptionWrapper
{
    public function __invoke(\Exception $e, Request $request, int $httpStatusCode): WrappedExceptionInfo
    {
        mtrace($e, "Fallback handling exception: ");
        
        $caughtException = new WrappedExceptionInfo($e, $httpStatusCode);
        $this->furtherProcessException($caughtException, $e);
        
        return $caughtException;
    }
    
    protected function furtherProcessException(WrappedExceptionInfo $info, \Exception $e): void
    {
        switch (true) {
            case ($e instanceof ExistenceViolationException):
                $info->setCode(Response::HTTP_NOT_FOUND);
                $info->setAttribute('key', $e->getFieldName());
                break;
            case ($e instanceof DataValidationException):
                $info->setCode(Response::HTTP_BAD_REQUEST);
                $info->setAttribute('key', $e->getFieldName());
                break;
        }
        
    }
}
