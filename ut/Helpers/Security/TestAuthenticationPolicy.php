<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 15:58
 */

namespace Oasis\Mlib\Http\Ut\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AuthenticationPolicyInterface;
use Silex\Application;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Provider\SimpleAuthenticationProvider;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Http\Firewall\SimplePreAuthenticationListener;

class TestAuthenticationPolicy implements AuthenticationPolicyInterface
{
    /** @var  SimplePreAuthenticatorInterface */
    protected $preAuthenticator;

    public function getAuthenticationType()
    {
        return self::AUTH_TYPE_PRE_AUTH;
    }

    /**
     * If string is returned, it must be either "anonymous" or "dao"
     *
     * @param Application $app
     * @param             $firewallName
     * @param             $options
     *
     * @return string|AuthenticationProviderInterface
     */
    public function getAuthenticationProvider(Application $app, $firewallName, $options)
    {
        return new SimpleAuthenticationProvider(
            $this->getPreAuthenticator(),
            $app['security.user_provider.' . $firewallName],
            $firewallName
        );
    }

    /**
     * @param Application                    $app
     * @param                                $firewallName
     * @param                                $options
     *
     * @return ListenerInterface
     */
    public function getAuthenticationListener(Application $app,
                                              $firewallName,
                                              $options)
    {
        return new SimplePreAuthenticationListener(
            $app['security.token_storage'],
            $app['security.authentication_manager'],
            $firewallName,
            $this->getPreAuthenticator()
        );
    }

    /**
     * @param Application $app
     * @param             $name
     * @param             $options
     *
     * @return AuthenticationEntryPointInterface
     */
    public function getEntryPoint(Application $app, $name, $options)
    {
        return null;
    }

    /**
     * @return SimplePreAuthenticatorInterface
     */
    public function getPreAuthenticator()
    {
        return $this->preAuthenticator;
    }

    /**
     * @param SimplePreAuthenticatorInterface $preAuthenticator
     */
    public function setPreAuthenticator($preAuthenticator)
    {
        $this->preAuthenticator = $preAuthenticator;
    }
}
