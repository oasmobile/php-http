<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 11:17
 */

namespace Oasis\Mlib\Http\Ut\Controllers;

use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use Oasis\Mlib\Http\Views\AbstractSmartViewHandler;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

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
            'slug'   => $slug,
        ];
    }

    public function paramInjected(JsonViewHandler $handler)
    {
        return [
            'called'  => $this->createTestString(__CLASS__, __FUNCTION__),
            'handler' => get_class($handler),
        ];
    }

    public function paramInjectedWithInheritedClass(AbstractSmartViewHandler $handler)
    {
        return [
            'called'  => $this->createTestString(__CLASS__, __FUNCTION__),
            'handler' => get_class($handler),
        ];
    }

    public function cookieSetter(ResponseCookieContainer $cookies)
    {
        $cookies->addCookie(new Cookie('name', 'John'));

        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
        ];
    }

    public function cookieChecker(Request $request)
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
            'name'   => $request->cookies->get('name'),
        ];
    }

    protected function createTestString($class, $function)
    {
        return $class . "::" . $function . "()";
    }
    
}
