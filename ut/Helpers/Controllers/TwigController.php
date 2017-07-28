<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-25
 * Time: 14:15
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\SilexKernel;

class TwigController
{
    public function a(SilexKernel $kernel)
    {
        return $kernel->render('a.twig', ['lala' => "hello"]);
    }

    public function a2(SilexKernel $kernel)
    {
        return $kernel->render('a2.twig', ['lala' => "WOW"]);
    }
}
