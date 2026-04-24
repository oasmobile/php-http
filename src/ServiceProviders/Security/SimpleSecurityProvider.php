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
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Utils\DataProviderInterface;

/**
 * Security provider rewritten for Symfony 7.x / MicroKernel.
 *
 * Silex SecurityServiceProvider inheritance and Pimple dependency have been removed.
 * This class retains the configuration API (addFirewall, addAccessRule, addAuthenticationPolicy,
 * addRoleHierarchy) and the register() method for MicroKernel integration.
 *
 * Note: The actual security firewall/authenticator system is NOT functional in Phase 1.
 * This is a minimal compilable adaptation. Authenticator system rewrite is Phase 3 (PRP-005).
 */
class SimpleSecurityProvider
{
    use ConfigurationValidationTrait;
    
    /** @var MicroKernel|null */
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
    
    // --- end of intermediate variables ---
    
    /** @var DataProviderInterface|null */
    protected $configDataProvider = null;
    
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
    
    /**
     * Register security services into MicroKernel.
     *
     * Processes the security configuration (merging programmatic additions with
     * config-based settings) and stores the validated config data provider.
     *
     * @param MicroKernel $kernel
     * @param array       $securityConfig The security config from Bootstrap_Config (optional)
     */
    public function register(MicroKernel $kernel, array $securityConfig = [])
    {
        $this->kernel = $kernel;
        
        // Merge programmatic additions into config
        if ($this->authPolicies) {
            $securityConfig['policies'] = array_merge(
                isset($securityConfig['policies']) ? $securityConfig['policies'] : [],
                $this->authPolicies
            );
        }
        if ($this->firewalls) {
            $securityConfig['firewalls'] = array_merge(
                isset($securityConfig['firewalls']) ? $securityConfig['firewalls'] : [],
                $this->firewalls
            );
        }
        if ($this->accessRules) {
            $securityConfig['access_rules'] = array_merge(
                isset($securityConfig['access_rules']) ? $securityConfig['access_rules'] : [],
                $this->accessRules
            );
        }
        if ($this->roleHierarchy) {
            $securityConfig['role_hierarchy'] = array_merge(
                isset($securityConfig['role_hierarchy']) ? $securityConfig['role_hierarchy'] : [],
                $this->roleHierarchy
            );
        }
        
        $this->configDataProvider = $this->processConfiguration($securityConfig, new SecurityConfiguration());
    }
    
    /** @return DataProviderInterface */
    public function getConfigDataProvider()
    {
        if (!$this->configDataProvider) {
            throw new \LogicException("Cannot get config data provider before registration");
        }
        
        return $this->configDataProvider;
    }
    
    /**
     * Get the parsed firewalls configuration.
     *
     * @return array
     */
    public function getFirewalls(): array
    {
        $dp = $this->getConfigDataProvider();
        $firewalls = $dp->getOptional('firewalls', DataProviderInterface::ARRAY_TYPE, []);
        
        $result = [];
        foreach ($firewalls as $firewallName => $firewall) {
            if (!$firewall instanceof FirewallInterface) {
                $firewall = new SimpleFirewall($firewall);
            }
            $result[$firewallName] = $this->parseFirewall($firewall);
        }
        
        return $result;
    }
    
    /**
     * Get the parsed access rules configuration.
     *
     * @return array
     */
    public function getAccessRules(): array
    {
        $dp = $this->getConfigDataProvider();
        $rules = $dp->getOptional('access_rules', DataProviderInterface::ARRAY_TYPE, []);
        
        $result = [];
        foreach ($rules as $rule) {
            if (!$rule instanceof AccessRuleInterface) {
                $rule = new SimpleAccessRule($rule);
            }
            $result[] = [
                $rule->getPattern(),
                $rule->getRequiredRoles(),
                $rule->getRequiredChannel(),
            ];
        }
        
        return $result;
    }
    
    /**
     * Get the parsed role hierarchy configuration.
     *
     * @return array
     */
    public function getRoleHierarchy(): array
    {
        $dp = $this->getConfigDataProvider();
        $hierarchy = $dp->getOptional('role_hierarchy', DataProviderInterface::ARRAY_TYPE, []);
        
        $result = [];
        foreach ($hierarchy as $parentName => $children) {
            $old = isset($result[$parentName]) ? $result[$parentName] : [];
            $old = array_merge($old, (array)$children);
            $result[$parentName] = $old;
        }
        
        return $result;
    }
    
    /**
     * Get the parsed authentication policies.
     *
     * @return AuthenticationPolicyInterface[]
     */
    public function getPolicies(): array
    {
        $dp = $this->getConfigDataProvider();
        
        return $dp->getOptional('policies', DataProviderInterface::ARRAY_TYPE, []);
    }
    
    /**
     * Parses firewall into array data.
     *
     * @param FirewallInterface $firewall
     *
     * @return array
     */
    protected function parseFirewall(FirewallInterface $firewall)
    {
        $setting              = $firewall->getPolicies();
        $setting['pattern']   = $firewall->getPattern();
        $setting['users']     = $firewall->getUserProvider();
        $setting['stateless'] = $firewall->isStateless();
        $setting              = array_merge($setting, $firewall->getOtherSettings());
        
        return $setting;
    }
}
