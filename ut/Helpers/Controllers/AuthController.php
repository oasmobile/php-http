<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-10
 * Time: 19:26
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\MicroKernel;

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

    public function madmin(MicroKernel $app)
    {

        return [
            'admin'  => $app->isGranted('ROLE_ADMIN'),
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function madminGood(MicroKernel $app)
    {
        return [
            'user'   => $app->getUser(),
            'right'  => $app->isGranted('ROLE_GOOD'),
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function madminParent(MicroKernel $app)
    {
        return [
            'user'   => $app->getUser(),
            'right'  => $app->isGranted('ROLE_PARENT'),
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function madminChild(MicroKernel $app)
    {
        return [
            'user'   => $app->getUser(),
            'right'  => $app->isGranted('ROLE_CHILD'),
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    protected function createTestString($class, $function)
    {
        return $class . "::" . $function . "()";
    }
}
