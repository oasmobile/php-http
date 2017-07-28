<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 17:41
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\SilexKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;

class ZxcController
{
    public function home($game, $lang, Request $request, SilexKernel $kernel)
    {
        /** @var UrlGenerator $ug */
        $ug  = $kernel['url_generator'];
        $url = $ug->generate('play.server', ['lang' => $lang, 'game' => $game]);

        return $kernel->redirect($url);
        //
        //return [
        //    "I got this message" =>
        //        "lang = $lang, game = $game",
        //    "request"            => $request->getMethod(),
        //];
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
