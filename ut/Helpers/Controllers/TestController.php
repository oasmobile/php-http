<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 11:17
 */

namespace Oasis\Mlib\Http\Ut\Controllers;

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

    public function paramDomain($game)
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
            'game'   => $game,
        ];
    }

    public function paramId($id)
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
            'id'     => $id,
        ];
    }

    public function paramSlug($slug)
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
            'slug'     => $slug,
        ];
    }

    protected function createTestString($class, $function)
    {
        return $class . "::" . $function . "()";
    }
    
}
