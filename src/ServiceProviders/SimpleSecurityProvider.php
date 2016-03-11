<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-11
 * Time: 17:40
 */

namespace Oasis\Mlib\Http\ServiceProviders;

use Silex\Application;
use Silex\Provider\SecurityServiceProvider;
use Symfony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;

class SimpleSecurityProvider extends SecurityServiceProvider
{
    /** @var SimpleAuthenticationPolicy[] */
    protected $authPolicies = [];

    public function register(Application $app)
    {
        parent::register($app);

        $preAuthFactory                                           = $app['security.authentication_listener.factory.pre_auth'];
        $app['security.authentication_listener.factory.pre_auth'] = $app->protect(
            function ($name, $options) use ($app, $preAuthFactory) {
                //$preAuthFactory = $app['security.authentication_listener.factory.pre_auth'];
                $result     = $preAuthFactory($name, $options);
                $entryPoint = $result[2];
                if (!$entryPoint) {
                    $app['security.entry_point.' . $name . '.pre_auth'] = new BasicAuthenticationEntryPoint('google!');
                    $result[2]                                          = 'security.entry_point.' . $name . '.pre_auth';
                }

                return $result;
            }
        );

        foreach ($this->authPolicies as $typeName => $policy) {
            $this->addAuthenticationFactory($typeName, $app);
        }
    }

    public function addAuthenticationPolicy($typeName, SimpleAuthenticationPolicy $policy)
    {
        $this->authPolicies[$typeName] = $policy;
    }

    protected function addAuthenticationFactory($typeName, Application $app)
    {
        if (!isset($this->authPolicies[$typeName])) {
            throw new \LogicException("Policy $typeName not pre-set");
        }

        /** @var SimpleAuthenticationPolicy $policy */
        $policy = $this->authPolicies[$typeName];

        $factoryName       = 'security.authentication_listener.factory.' . $typeName;
        $app[$factoryName] = $app->protect(
            function ($firewallName, $options) use ($typeName, $policy, $app) {

                $provider       = $policy->isAnonymousAllowed() ? 'anonymous' : 'dao';
                $authProviderId = 'security.authentication_provider.' . $firewallName . '.' . $provider;
                if (!isset($app[$authProviderId])) {
                    $app[$authProviderId] = $app['security.authentication_provider.' . $provider . '._proto'](
                        $firewallName
                    );
                }

                $authListenerId = 'security.authentication_listener.' . $firewallName . '.' . $typeName;
                if (!isset($app[$authListenerId])) {
                    $app[$authListenerId] = $app->share(
                        function () use ($policy, $firewallName, $options) {
                            $factory = $policy->getListnerFactory();

                            return $factory($firewallName, $options);
                        }
                    );
                }

                $entryId = 'security.entry_point.' . $firewallName;
                if (!isset($app[$entryId])) {
                    $app[$entryId] = $app->share(
                        function () use ($policy, $firewallName, $options) {
                            $factory = $policy->getEntryPointFactory();

                            return $factory($firewallName, $options);
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
    
}
