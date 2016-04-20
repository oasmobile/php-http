<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-04-20
 * Time: 22:36
 */

namespace Oasis\Mlib\Http\ServiceProviders\Cookie;

use Symfony\Component\HttpFoundation\Cookie;

class ResponseCookieContainer
{
    /** @var Cookie[] */
    protected $cookies = [];
    
    public function addCookie(Cookie $cookie){
        $this->cookies[] = $cookie;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Cookie[]
     */
    public function getCookies() {
        return $this->cookies;
    }
}
