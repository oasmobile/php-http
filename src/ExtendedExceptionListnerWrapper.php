<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 16:59
 */

namespace Oasis\Mlib\Http;

use Silex\ExceptionListenerWrapper;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class ExtendedExceptionListnerWrapper extends ExceptionListenerWrapper
{
    protected function ensureResponse($response, GetResponseForExceptionEvent $event)
    {
        if ($response === null && $event->getResponse() === null) {
            // do not ensure response if error/exception handler returns null and there was no response in $event either
            return;
        }

        parent::ensureResponse($response, $event);
    }
    
}
