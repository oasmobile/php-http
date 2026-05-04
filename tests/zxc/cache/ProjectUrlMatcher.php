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

        $host = $this->context->getHost();

        if (preg_match('#^(?P<game>[^\\.]++)\\.test\\.oasgames\\.com$#si', $host, $hostMatches)) {
            // home
            if (preg_match('#^/(?P<lang>[^/]++)/$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'home')), array (  '_controller' => 'Oasis\\Mlib\\Http\\Test\\Helpers\\ZxcController::home',));
            }

            // play.server
            if (preg_match('#^/(?P<lang>[^/]++)/play/server/$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'play.server')), array (  '_controller' => 'Oasis\\Mlib\\Http\\Test\\Helpers\\ZxcController::playServer',));
            }

            // article
            if (preg_match('#^/(?P<lang>[^/]++)/article/(?P<name>[^/\\.]++)\\.html$#s', $pathinfo, $matches)) {
                return $this->mergeDefaults(array_replace($hostMatches, $matches, array('_route' => 'article')), array (  '_controller' => 'ZxcController::article',));
            }

        }

        throw 0 < count($allow) ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
