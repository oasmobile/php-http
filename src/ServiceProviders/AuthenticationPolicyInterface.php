<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-11
 * Time: 19:50
 */

namespace Oasis\Mlib\Http\ServiceProviders;

use Silex\Application;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

interface AuthenticationPolicyInterface
{
    const AUTH_TYPE_LOGOUT      = "logout";
    const AUTH_TYPE_PRE_AUTH    = "pre_auth";
    const AUTH_TYPE_FORM        = "form";
    const AUTH_TYPE_HTTP        = "http";
    const AUTH_TYPE_REMEMBER_ME = "remember_me";
    const AUTH_TYPE_ANONYMOUS   = "anonymous";

    /**
     * If anonymous is allowed in this policy
     *
     * @return bool
     */
    public function isAnonymousAllowed();

    public function getAuthenticationType();
    
    /**
     * @param Application $app
     * @param             $name
     * @param             $options
     *
     * @return ListenerInterface
     */
    public function getAuthenticationListener(Application $app, $name, $options);

    /**
     * @param Application $app
     * @param             $name
     * @param             $options
     *
     * @return AuthenticationEntryPointInterface
     */
    public function getEntryPoint(Application $app, $name, $options);

}
