<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 17:41
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;

class ZxcController
{
    public function home($game, $lang, Request $request, MicroKernel $kernel)
    {
        /** @var UrlGenerator $ug */
        $ug  = $kernel->getUrlGenerator();
        $url = $ug->generate('play.server', ['lang' => $lang, 'game' => $game]);

        return new RedirectResponse($url);
    }

    public function playServer($game, $lang)
    {
        return [
            "happy game server!",
            "game" => $game,
            "lang" => $lang
        ];
    }
}
