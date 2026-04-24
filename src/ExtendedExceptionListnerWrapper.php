<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 16:59
 */

namespace Oasis\Mlib\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Extended exception listener wrapper that preserves the original Silex behavior:
 * when the error handler returns null AND the event has no response, the exception
 * continues to propagate (no forced empty response).
 *
 * In Symfony 7.x this replaces the old Silex\ExceptionListenerWrapper base class.
 */
class ExtendedExceptionListnerWrapper
{
    protected function ensureResponse($response, ExceptionEvent $event): void
    {
        if ($response === null && $event->getResponse() === null) {
            // do not ensure response if error/exception handler returns null and there was no response in $event either
            return;
        }

        if ($response instanceof Response) {
            $event->setResponse($response);
        }
    }
}
