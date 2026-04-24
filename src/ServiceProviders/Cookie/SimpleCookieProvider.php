<?php

namespace Oasis\Mlib\Http\ServiceProviders\Cookie;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SimpleCookieProvider implements EventSubscriberInterface
{
    protected ResponseCookieContainer $cookieContainer;

    public function __construct(?ResponseCookieContainer $cookieContainer = null)
    {
        $this->cookieContainer = $cookieContainer ?? new ResponseCookieContainer();
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', 0]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        foreach ($this->cookieContainer->getCookies() as $cookie) {
            $event->getResponse()->headers->setCookie($cookie);
        }
    }

    public function getCookieContainer(): ResponseCookieContainer
    {
        return $this->cookieContainer;
    }
}
