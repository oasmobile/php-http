<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 14:17
 */

namespace Oasis\Mlib\Http\Ut\Controllers;

class SubTestController extends TestController
{
    public function home() {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }
}
