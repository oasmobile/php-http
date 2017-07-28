<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 11:17
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

use Oasis\Mlib\Http\ChainedParameterBagDataProvider;
use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use Oasis\Mlib\Http\Views\AbstractSmartViewHandler;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Oasis\Mlib\Utils\DataProviderInterface;
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
    
    public function paramConfigValue($one, $two, $three)
    {
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
            'one'    => $one,
            'two'    => $two,
            'three'  => $three,
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
    
    public function paramChained($id, Request $request)
    {
        $chainedBag = new ChainedParameterBagDataProvider($request->attributes, $request->query, $request->request);
        
        $name   = $chainedBag->getMandatory('name');
        $age    = $chainedBag->getMandatory('age', DataProviderInterface::INT_TYPE);
        $salary = $chainedBag->getOptional('salary', DataProviderInterface::FLOAT_TYPE, 999.99);
        
        return [
            'called' => $this->createTestString(__CLASS__, __FUNCTION__),
            'id'     => $id,
            'name'   => $name,
            'age'    => $age,
            'salary' => $salary,
        ];
    }
    
    public function proxyTest(Request $request)
    {
        return [
            'from' => $request->getClientIp(),
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
