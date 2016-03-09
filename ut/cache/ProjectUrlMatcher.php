<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * ProjectUrlMatcher.
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    /**
     * Constructor.
     */
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($pathinfo)
    {
        $allow = array();
        $pathinfo = rawurldecode($pathinfo);
        $context = $this->context;
        $request = $this->request;

        // home
        if ($pathinfo === '/') {
            return array (  '_controller' => 'TestController::home',  '_route' => 'home',);
        }

        $host = $this->context->getHost();

        if (preg_match('#^localhost$#si', $host, $hostMatches)) {
            // domain.test.localhost
            if ($pathinfo === '/domain') {
                return array (  '_controller' => 'TestController::domainLocalhost',  '_route' => 'domain.test.localhost',);
            }

        }

        if (preg_match('#^baidu\\.com$#si', $host, $hostMatches)) {
            // domain.test.baidu
            if ($pathinfo === '/domain') {
                return array (  '_controller' => 'TestController::domainBaidu',  '_route' => 'domain.test.baidu',);
            }

        }

        // sub.home
        if ($pathinfo === '/sub/') {
            return array (  '_controller' => 'SubTestController::home',  '_route' => 'sub.home',);
        }

        if (0 === strpos($pathinfo, '/cors')) {
            // cors.home
            if ($pathinfo === '/cors/home') {
                return array (  '_controller' => 'TestController::corsHome',  '_route' => 'cors.home',);
            }

            // cors.put
            if ($pathinfo === '/cors/put') {
                if ($this->context->getMethod() != 'PUT') {
                    $allow[] = 'PUT';
                    goto not_corsput;
                }

                return array (  '_controller' => 'TestController::corsHome',  '_route' => 'cors.put',);
            }
            not_corsput:

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
