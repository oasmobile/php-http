<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-16
 * Time: 15:52
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Abstract stub for pre-authentication policy.
 *
 * In Symfony 6.0+, SimpleAuthenticationProvider, SimplePreAuthenticationListener,
 * and ListenerInterface (Security\Http\Firewall) have been removed.
 * This class is now an abstract stub — all methods that previously depended on
 * those removed APIs are declared abstract, forcing downstream to provide
 * implementations in Phase 3 (PRP-005).
 */
abstract class AbstractSimplePreAuthenticationPolicy implements AuthenticationPolicyInterface
{
    public function getAuthenticationType()
    {
        return self::AUTH_TYPE_PRE_AUTH;
    }
    
    /**
     * Must be implemented by downstream in Phase 3.
     * Previously returned a SimpleAuthenticationProvider instance (removed in Symfony 6.0).
     *
     * @param MicroKernel $kernel
     * @param             $firewallName
     * @param             $options
     *
     * @return string|AuthenticationProviderInterface
     */
    abstract public function getAuthenticationProvider(MicroKernel $kernel, $firewallName, $options);
    
    /**
     * Must be implemented by downstream in Phase 3.
     * Previously returned a SimplePreAuthenticationListener instance (removed in Symfony 6.0).
     *
     * @param MicroKernel $kernel
     * @param             $firewallName
     * @param             $options
     *
     * @return mixed
     */
    abstract public function getAuthenticationListener(MicroKernel $kernel,
                                                       $firewallName,
                                                       $options);
    
    /**
     * @param MicroKernel $kernel
     * @param             $name
     * @param             $options
     *
     * @return AuthenticationEntryPointInterface
     */
    public function getEntryPoint(MicroKernel $kernel, $name, $options)
    {
        return new NullEntryPoint();
    }
}
