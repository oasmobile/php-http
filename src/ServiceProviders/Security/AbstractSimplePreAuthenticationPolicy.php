<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-16
 * Time: 15:52
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Pimple\Container;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Provider\SimpleAuthenticationProvider;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Http\Firewall\SimplePreAuthenticationListener;

abstract class AbstractSimplePreAuthenticationPolicy implements AuthenticationPolicyInterface
{
    public function getAuthenticationType()
    {
        return self::AUTH_TYPE_PRE_AUTH;
    }
    
    /**
     * If string is returned, it must be either "anonymous" or "dao"
     *
     * @param Container   $app
     * @param             $firewallName
     * @param             $options
     *
     * @return string|AuthenticationProviderInterface
     */
    public function getAuthenticationProvider(Container $app, $firewallName, $options)
    {
        return new SimpleAuthenticationProvider(
            $this->getPreAuthenticator(),
            $this->getUserProvider($app, $firewallName),
            $firewallName
        );
    }
    
    /**
     * @param Container                      $app
     * @param                                $firewallName
     * @param                                $options
     *
     * @return ListenerInterface
     */
    public function getAuthenticationListener(Container $app,
                                              $firewallName,
                                              $options)
    {
        return new SimplePreAuthenticationListener(
            $app['security.token_storage'],
            $app['security.authentication_manager'],
            $firewallName,
            $this->getPreAuthenticator(),
            $app['logger']
        );
    }
    
    /**
     * @param Container   $app
     * @param             $name
     * @param             $options
     *
     * @return AuthenticationEntryPointInterface
     */
    public function getEntryPoint(Container $app, $name, $options)
    {
        return new NullEntryPoint();
    }
    
    protected function getUserProvider(Container $app, $firewallName)
    {
        return $app['security.user_provider.' . $firewallName];
    }
    
    /**
     * @return SimplePreAuthenticatorInterface
     */
    abstract protected function getPreAuthenticator();
    
}
