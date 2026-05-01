<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 17:43
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

class FallbackController
{
    public function okAction()
    {
        return "Hello world!";
    }
    
    public function errorAction()
    {
        throw new \RuntimeException("Oops!");
    }
}
