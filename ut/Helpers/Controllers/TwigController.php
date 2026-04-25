<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-25
 * Time: 14:15
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Response;

class TwigController
{
    public function a(MicroKernel $kernel)
    {
        $twig = $kernel->getTwig();

        return new Response($twig->render('a.twig', ['lala' => "hello"]));
    }

    public function a2(MicroKernel $kernel)
    {
        $twig = $kernel->getTwig();

        return new Response($twig->render('a2.twig', ['lala' => "WOW"]));
    }
}
