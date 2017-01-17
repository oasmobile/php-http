<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 17:43
 */

namespace Oasis\Mlib\Http\Ut\Controllers;

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
