<?php
namespace Oasis\Mlib\Http\Ut;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 10:44
 */
class CorsController
{
    public function home()
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function put()
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
