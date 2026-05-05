<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Kernel;

use Oasis\Mlib\Http\ServiceProviders\Cookie\ResponseCookieContainer;
use Oasis\Mlib\Http\ServiceProviders\Cookie\SimpleCookieProvider;
use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\ServiceProviders\Twig\SimpleTwigServiceProvider;
use Oasis\Mlib\Utils\DataType;

/**
 * Service provider registration extracted from MicroKernel.
 */
trait ServicesTrait
{
    protected function registerCookie(): void
    {
        $cookieContainer  = new ResponseCookieContainer();
        $this->addControllerInjectedArg($cookieContainer);

        $cookieSubscriber = new SimpleCookieProvider($cookieContainer);
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher       = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addSubscriber($cookieSubscriber);
    }

    protected function registerCors(): void
    {
        $corsConfig = $this->httpDataProvider->getOptional('cors', DataType::Mixed);

        if (!$corsConfig || !\is_array($corsConfig)) {
            return;
        }

        $this->corsSubscriber = new CrossOriginResourceSharingProvider($corsConfig);
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addSubscriber($this->corsSubscriber);
    }

    protected function registerTwig(): void
    {
        $twigConfig = $this->httpDataProvider->getOptional('twig', DataType::Mixed);

        if (!$twigConfig || !\is_array($twigConfig)) {
            return;
        }

        $twigProvider = new SimpleTwigServiceProvider();
        $twigProvider->register($this, $twigConfig);
    }

    protected function registerSecurity(): void
    {
        $securityConfig = $this->httpDataProvider->getOptional('security', DataType::Mixed);

        if (!$securityConfig || !\is_array($securityConfig)) {
            return;
        }

        $securityProvider = new SimpleSecurityProvider();
        $securityProvider->register($this, $securityConfig);
    }
}
