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
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Utils\DataProviderInterface;
use Silex\Application;
use Silex\Provider\SecurityServiceProvider;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class SimpleSecurityProvider extends SecurityServiceProvider
{
    use ConfigurationValidationTrait;
    
    /** @var SilexKernel */
    protected $kernel = null;
    
    // --- start of intermediate variables holding config data ---
    
    /** @var FirewallInterface[]|array */
    protected $firewalls = [];
    /** @var AccessRuleInterface[]|array */
    protected $accessRules = [];
    /** @var AuthenticationPolicyInterface[] */
    protected $authPolicies = [];
    /** @var array */
    protected $roleHierarchy = [];
    
    // --- end of intermidate variables ---
    
    public function __construct()
    {
    }
    
    /**
     * @param AccessRuleInterface|array $rule
     */
    public function addAccessRule($rule)
    {
        $this->accessRules[] = $rule;
    }
    
    public function addAuthenticationPolicy($policyName, AuthenticationPolicyInterface $policy)
    {
        $this->authPolicies[$policyName] = $policy;
    }
    
    public function addFirewall($firewallName, $firewall)
    {
        $this->firewalls[$firewallName] = $firewall;
    }
    
    public function addRoleHierarchy($role, $children)
    {
        $old = isset($this->roleHierarchy[$role]) ? $this->roleHierarchy[$role] : [];
        $old = array_merge($old, (array)$children);
        
        $this->roleHierarchy[$role] = $old;
    }
    
    public function boot(Application $app)
    {
        // install additional policies
        foreach ($app['security.config.policies'] as $policyName => $policy) {
            $this->installAuthenticationFactory($policyName, $policy, $app);
        }
        
        $firewallSetting = [];
        foreach ($app['security.config.firewalls'] as $firewallName => $firewall) {
            if (!$firewall instanceof FirewallInterface) {
                $firewall = new SimpleFirewall($firewall);
            }
            $firewallSetting[$firewallName] = $this->parseFirewall($firewall, $app);
        }
        $app['security.firewalls'] = $firewallSetting;
        
        $rulesSetting = [];
        foreach ($app['security.config.access_rules'] as $rule) {
            if (!$rule instanceof AccessRuleInterface) {
                $rule = new SimpleAccessRule($rule);
            }
            $rulesSetting[] = [
                $rule->getPattern(),
                $rule->getRequiredRoles(),
                $rule->getRequiredChannel(),
            ];
        }
        $app['security.access_rules'] = $rulesSetting;
        
        $rolesSetting = [];
        foreach ($app['security.config.role_hierarchy'] as $parentName => $children) {
            $old = isset($rolesSetting[$parentName]) ? $rolesSetting[$parentName] : [];
            $old = array_merge($old, (array)$children);
            
            $rolesSetting[$parentName] = $old;
        }
        $app['security.role_hierarchy'] = $rolesSetting;
        
        parent::boot($app);
        
    }
    
    public function register(Application $app)
    {
        $this->kernel = $app;
        
        $app['security.config.data_provider']  = $app->share(
            function ($app) {
                $config = isset($app['security.config']) ? $app['security.config'] : [];
                if ($this->authPolicies) {
                    $config['policies'] = array_merge(
                        isset($config['policies']) ? $config['policies'] : [],
                        $this->authPolicies
                    );
                }
                if ($this->firewalls) {
                    $config['firewalls'] = array_merge(
                        isset($config['firewalls']) ? $config['firewalls'] : [],
                        $this->firewalls
                    );
                }
                if ($this->accessRules) {
                    $config['access_rules'] = array_merge(
                        isset($config['access_rules']) ? $config['access_rules'] : [],
                        $this->accessRules
                    );
                }
                if ($this->authPolicies) {
                    $config['role_hierarchy'] = array_merge(
                        isset($config['role_hierarchy']) ? $config['role_hierarchy'] : [],
                        $this->roleHierarchy
                    );
                }
                
                $dp = $this->processConfiguration($config, new SecurityConfiguration());
                
                return $dp;
            }
        );
        $app['security.config.policies']       = $app->share(
            function () {
                return $this->getConfigDataProvider()->getOptional(
                    'policies',
                    DataProviderInterface::ARRAY_TYPE,
                    []
                );
            }
        );
        $app['security.config.firewalls']      = $app->share(
            function () {
                return $this->getConfigDataProvider()->getOptional(
                    'firewalls',
                    DataProviderInterface::ARRAY_TYPE,
                    []
                );
            }
        );
        $app['security.config.access_rules']   = $app->share(
            function () {
                return $this->getConfigDataProvider()->getOptional(
                    'access_rules',
                    DataProviderInterface::ARRAY_TYPE,
                    []
                );
            }
        );
        $app['security.config.role_hierarchy'] = $app->share(
            function () {
                return $this->getConfigDataProvider()->getOptional(
                    'role_hierarchy',
                    DataProviderInterface::ARRAY_TYPE,
                    []
                );
            }
        );
        parent::register($app);
    }
    
    /** @return DataProviderInterface */
    public function getConfigDataProvider()
    {
        if (!$this->kernel) {
            throw new \LogicException("Cannot get config data provider before registration");
        }
        
        return $this->kernel['security.config.data_provider'];
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
    
    /**
     *
     * Parses firewall into silex compatible array data
     *
     * @param FirewallInterface $firewall
     * @param Application       $app
     *
     * @return array
     */
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
    
}
