<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-05
 * Time: 14:57
 */

namespace Oasis\Mlib\Http\ErrorHandlers;

use Oasis\Mlib\Utils\Exceptions\DataValidationException;
use Symfony\Component\HttpFoundation\Response;

class ExceptionWrapper
{
    function __invoke(\Exception $e, $code)
    {
        mtrace($e, "Fallback handling exception: ");
        
        $caughtException = new WrappedExceptionInfo($e, $code);
        $this->furtherProcessException($caughtException, $e);
        
        return $caughtException;
    }
    
    protected function furtherProcessException(WrappedExceptionInfo $info, \Exception $e)
    {
        switch (true) {
            case ($e instanceof DataValidationException):
                $info->setCode(Response::HTTP_BAD_REQUEST);
                $info->setAttribute('key', $e->getFieldName());
                break;
        }
        
    }
}
