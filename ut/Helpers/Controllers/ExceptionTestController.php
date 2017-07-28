<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-19
 * Time: 11:55
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\Exceptions\UniquenessViolationHttpException;

class ExceptionTestController
{
    public function throwUniquenessViolationExceptionAction()
    {
        throw new UniquenessViolationHttpException("something exists!");
    }
    
}
