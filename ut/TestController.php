<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 11:17
 */

namespace Oasis\Mlib\Http\Ut;

class TestController
{
    public function home()
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function domainLocalhost()
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function domainBaidu()
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function corsHome()
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    protected function createTestString($class, $function)
    {
        return $class . "::" . $function . "()";
    }
    
}
