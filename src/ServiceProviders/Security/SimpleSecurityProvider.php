<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-11
 * Time: 17:40
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\SecurityConfiguration;
use Oasis\Mlib\Utils\DataProviderInterface;
use Silex\Application;
use Silex\Provider\SecurityServiceProvider;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class SimpleSecurityProvider extends SecurityServiceProvider
{
    use ConfigurationValidationTrait;
    
    /** @var FirewallInterface[]|array */
    protected $firewalls = [];
    
    /** @var AccessRuleInterface[]|array */
    protected $accessRules = [];
    
    /** @var AuthenticationPolicyInterface[] */
    protected $authPolicies = [];
    
    protected $roleHierarchy = [];
    
    public function __construct()
    {
    }
    
    public function register(Application $app)
    {
        parent::register($app);
    }
    
    public function boot(Application $app)
    {
        if ($app['security.config']) {
            $dp = $this->processConfiguration($app['security.config'], new SecurityConfiguration());
            if ($policies = $dp->getOptional('policies', DataProviderInterface::ARRAY_TYPE, [])) {
                foreach ($policies as $name => $policy) {
                    if ($policy instanceof AuthenticationPolicyInterface) {
                        $this->addAuthenticationPolicy($name, $policy);
                    }
                }
            }
            if ($firewalls = $dp->getOptional('firewalls', DataProviderInterface::ARRAY_TYPE, [])) {
                foreach ($firewalls as $name => $firewallData) {
                    $firewall = new SimpleFirewall($firewallData);
                    $this->addFirewall($name, $firewall);
                }
            }
            if ($accessRules = $dp->getOptional('access_rules', DataProviderInterface::ARRAY_TYPE, [])) {
                foreach ($accessRules as $name => $ruleData) {
                    $rule = new SimpleAccessRule($ruleData);
                    $this->addAccessRule($rule);
                }
            }
            if ($roleHierarchy = $dp->getOptional('role_hierarchy', DataProviderInterface::ARRAY_TYPE, [])) {
                foreach ($roleHierarchy as $parent => $children) {
                    $this->addRoleHierarchy($parent, $children);
                }
            }
        }
    
        $app['security.role_hierarchy'] = $this->getRoleHierarchy();
    
        foreach ($this->authPolicies as $policyName => $policy) {
            $this->installAuthenticationFactory($policyName, $policy, $app);
        }
    
        $firewallSetting = [];
        foreach ($this->firewalls as $firewallName => $firewall) {
            if ($firewall instanceof FirewallInterface) {
                $firewallSetting[$firewallName] = $this->parseFirewall($firewall, $app);
            }
            else {
                $firewallSetting[$firewallName] = $firewall;
            }
        }
        $app['security.firewalls'] = $firewallSetting;
    
        $rulesSetting = [];
        foreach ($this->accessRules as $rule) {
            if ($rule instanceof AccessRuleInterface) {
                $rulesSetting[] = [
                    $rule->getPattern(),
                    $rule->getRequiredRoles(),
                    $rule->getRequiredChannel(),
                ];
            }
            else {
                $rulesSetting[] = $rule;
            }
        }
        $app['security.access_rules'] = $rulesSetting;
        parent::boot($app);
    
    }
    
    public function addAuthenticationPolicy($policyName, AuthenticationPolicyInterface $policy)
    {
        $this->authPolicies[$policyName] = $policy;
    }
    
    public function addFirewall($firewallName, $firewall)
    {
        $this->firewalls[$firewallName] = $firewall;
    }
    
    /**
     * @param AccessRuleInterface|array $rule
     */
    public function addAccessRule($rule)
    {
        $this->accessRules[] = $rule;
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
    
    protected function parseFirewall(FirewallInterface $firewall,
        /** @noinspection PhpUnusedParameterInspection */
                                     Application $app)
    {
        $setting              = $firewall->getPolicies();
        $setting['pattern']   = $firewall->getPattern();
        $setting['users']     = $firewall->getUserProvider();
        $setting['stateless'] = $firewall->isStateless();
        $setting              = array_merge($setting, $firewall->getOtherSettings());
        
        return $setting;
    }
    
    /**
     * @return array
     */
    public function getRoleHierarchy()
    {
        return $this->roleHierarchy;
    }
    
    public function addRoleHierarchy($role, $children)
    {
        $old = isset($this->roleHierarchy[$role]) ? $this->roleHierarchy[$role] : [];
        $old = array_merge($old, (array)$children);
        
        $this->roleHierarchy[$role] = $old;
    }
}
