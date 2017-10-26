<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 28/07/2017
 * Time: 4:26 PM
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Symfony\Component\HttpFoundation\Request;

class AwsController
{
    public function onElbForwarded(Request $request)
    {
        \mdebug("ok");
        
        return [
            'port'  => $request->getPort(),
            'https' => $request->isSecure(),
        ];
    }
    
    public function reportIp(Request $request) {
        return [
            'ip' => $request->getClientIp()
        ];
    }
}
