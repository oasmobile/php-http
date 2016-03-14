<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-11
 * Time: 17:40
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Silex\Application;
use Silex\Provider\SecurityServiceProvider;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class SimpleSecurityProvider extends SecurityServiceProvider
{
    /** @var FirewallInterface[] */
    protected $firewalls = [];

    /** @var AuthenticationPolicyInterface[] */
    protected $authPolicies = [];

    public function register(Application $app)
    {
        parent::register($app);

        //$preAuthFactory                                           = $app['security.authentication_listener.factory.pre_auth'];
        //$app['security.authentication_listener.factory.pre_auth'] = $app->protect(
        //    function ($name, $options) use ($app, $preAuthFactory) {
        //        list($authProviderId, $authListenerId, $entryPointId, $authType) = call_user_func(
        //            $preAuthFactory,
        //            $name,
        //            $options
        //        );
        //        if (!$entryPointId) {
        //            $app['security.entry_point.' . $name . '.pre_auth'] = new BasicAuthenticationEntryPoint('google!');
        //            $entryPointId                                       = 'security.entry_point.' . $name . '.pre_auth';
        //        }
        //
        //        return [
        //            $authProviderId,
        //            $authListenerId,
        //            $entryPointId,
        //            $authType,
        //        ];
        //    }
        //);

        foreach ($this->authPolicies as $policyName => $policy) {
            $this->installAuthenticationFactory($policyName, $policy, $app);
        }

        $firewallSetting = [];
        foreach ($this->firewalls as $firewallName => $firewall) {
            $firewallSetting[$firewallName] = $this->parseFirewall($firewall, $app);
        }
        $app['security.firewalls'] = $firewallSetting;

    }

    public function addAuthenticationPolicy($typeName, AuthenticationPolicyInterface $policy)
    {
        $this->authPolicies[$typeName] = $policy;
    }

    public function addFirewall($firewallName, FirewallInterface $firewall)
    {
        $this->firewalls[$firewallName] = $firewall;
    }

    protected function installAuthenticationFactory($policyName,
                                                    AuthenticationPolicyInterface $policy,
                                                    Application $app)
    {
        $factoryName       = 'security.authentication_listener.factory.' . $policyName;
        $app[$factoryName] = $app->protect(
            function ($firewallName, $options) use ($policyName, $policy, $app) {

                $authProviderId = 'security.authentication_provider.' . $firewallName . '.' . $policyName;
                if (!isset($app[$authProviderId])) {
                    $app[$authProviderId] = $app->share(
                        function () use ($policy, $app, $firewallName, $options) {
                            $provider = $policy->getAuthenticationProvider($app, $firewallName, $options);
                            if ($provider instanceof AuthenticationProviderInterface) {
                                return $provider;
                            }
                            else {
                                return $app['security.authentication_provider.' . $provider . '._proto'](
                                    $firewallName
                                );
                            }
                        }
                    );
                }

                $authListenerId = 'security.authentication_listener.' . $firewallName . '.' . $policyName;
                if (!isset($app[$authListenerId])) {
                    $app[$authListenerId] = $app->share(
                        function () use ($policy, $app, $firewallName, $options) {
                            return $policy->getAuthenticationListener(
                                $app,
                                $firewallName,
                                $options
                            );
                        }
                    );
                }

                $entryId = 'security.entry_point.' . $firewallName;
                if (!isset($app[$entryId])) {
                    $app[$entryId] = $app->share(
                        function () use ($policy, $app, $firewallName, $options) {
                            $entryPoint = $policy->getEntryPoint($app, $firewallName, $options);
                            if (!$entryPoint instanceof AuthenticationEntryPointInterface) {
                                $entryPoint = new NullEntryPoint();
                            }

                            return $entryPoint;
                        }
                    );

                }

                return [
                    $authProviderId,
                    $authListenerId,
                    $entryId,
                    $policy->getAuthenticationType(),
                ];
            }
        );
    }

    protected function parseFirewall(FirewallInterface $firewall, Application $app)
    {
        $setting            = $firewall->getPolicies();
        $setting['pattern'] = $firewall->getPattern();
        $setting['users']   = $firewall->getUserProvider();
        $setting            = array_merge($setting, $firewall->getOtherSettings());

        return $setting;
    }
}
