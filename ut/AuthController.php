<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-10
 * Time: 19:26
 */

namespace Oasis\Mlib\Http\Ut;

class AuthController
{
    public function admin()
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function fadmin()
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function padmin()
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }
    public function madmin()
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
