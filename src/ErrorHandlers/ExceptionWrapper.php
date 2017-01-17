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
        
        if ($e instanceof DataValidationException) {
            $caughtException->setCode(Response::HTTP_BAD_REQUEST);
            $caughtException->setAttribute('key', $e->getFieldName());
        }
        
        return $caughtException;
    }
}
