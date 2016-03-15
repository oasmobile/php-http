<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-10
 * Time: 19:26
 */

namespace Oasis\Mlib\Http\Ut\Controllers;

use Oasis\Mlib\Http\SilexKernel;
use Silex\Application;

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

    public function madmin(SilexKernel $app)
    {

        return [
            'admin'  => $app->isGranted('ROLE_ADMIN'),
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function madminGood(SilexKernel $app)
    {
        return [
            'user'   => $app->getUser(),
            'right'  => $app->isGranted('ROLE_GOOD'),
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function madminParent(SilexKernel $app)
    {
        return [
            'user'   => $app->getUser(),
            'right'  => $app->isGranted('ROLE_PARENT'),
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function madminChild(SilexKernel $app)
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
