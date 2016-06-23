<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 14:17
 */

namespace Oasis\Mlib\Http\Ut\Controllers;

use Symfony\Component\HttpFoundation\Request;

class SubTestController extends TestController
{
    public function sub(Request $request)
    {
        return [
            'attributes' => $request->attributes->all(),
            'called'     => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }
}
