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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * Security provider for Symfony 7.x / MicroKernel.
 *
 * Manages firewall, access rule, authentication policy, and role hierarchy
 * configuration. The register() method parses configuration, creates
 * TokenStorage / AuthorizationChecker, and registers firewall + access rule
 * event listeners on KernelEvents::REQUEST.
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
    public function addAccessRule(AccessRuleInterface|array $rule): void
    {
        $this->accessRules[] = $rule;
    }
    
    public function addAuthenticationPolicy(string $policyName, AuthenticationPolicyInterface $policy): void
    {
        $this->authPolicies[$policyName] = $policy;
    }
    
    public function addFirewall(string $firewallName, FirewallInterface|array $firewall): void
    {
        $this->firewalls[$firewallName] = $firewall;
    }
    
    public function addRoleHierarchy(string $role, string|array $children): void
    {
        $old = isset($this->roleHierarchy[$role]) ? $this->roleHierarchy[$role] : [];
        $old = array_merge($old, (array)$children);
        
        $this->roleHierarchy[$role] = $old;
    }
    
    /**
     * Register security services into MicroKernel.
     *
     * Processes the security configuration (merging programmatic additions with
     * config-based settings), creates TokenStorage and AuthorizationChecker,
     * and registers firewall + access rule event listeners.
     *
     * @param MicroKernel $kernel
     * @param array       $securityConfig The security config from Bootstrap_Config (optional)
     */
    public function register(MicroKernel $kernel, array $securityConfig = []): void
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
        
        // Create TokenStorage
        $tokenStorage = new TokenStorage();
        $kernel->setTokenStorage($tokenStorage);
        
        // Configure RoleHierarchy + AccessDecisionManager
        $roleHierarchy = new RoleHierarchy($this->getRoleHierarchy());
        $roleHierarchyVoter = new RoleHierarchyVoter($roleHierarchy);
        $accessDecisionManager = new AccessDecisionManager([$roleHierarchyVoter], new UnanimousStrategy());
        $authorizationChecker = new AuthorizationChecker($tokenStorage, $accessDecisionManager);
        $kernel->setAuthorizationChecker($authorizationChecker);
        
        // Register firewall event listener
        $this->registerFirewallListener($kernel, $tokenStorage);
        
        // Register access rule event listener
        $this->registerAccessRuleListener($kernel, $tokenStorage, $accessDecisionManager);
    }
    
    /** @return DataProviderInterface */
    public function getConfigDataProvider(): DataProviderInterface
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
    protected function parseFirewall(FirewallInterface $firewall): array
    {
        $setting              = $firewall->getPolicies();
        $setting['pattern']   = $firewall->getPattern();
        $setting['users']     = $firewall->getUserProvider();
        $setting['stateless'] = $firewall->isStateless();
        $setting              = array_merge($setting, $firewall->getOtherSettings());
        
        return $setting;
    }
    
    /**
     * Register firewall event listener to KernelEvents::REQUEST.
     *
     * Uses BEFORE_PRIORITY_FIREWALL (= 8) priority. For each incoming main
     * request, iterates firewalls in registration order; the first matching
     * firewall's policies are evaluated. For each policy, the authenticator's
     * supports() → authenticate() → createToken() chain is invoked.
     * AuthenticationException is caught silently (token stays null).
     */
    protected function registerFirewallListener(MicroKernel $kernel, TokenStorageInterface $tokenStorage): void
    {
        $firewalls = $this->getFirewalls();
        $policies = $this->getPolicies();
        
        $kernel->getContainer()->get('event_dispatcher')->addListener(
            KernelEvents::REQUEST,
            function (RequestEvent $event) use ($kernel, $firewalls, $policies, $tokenStorage) {
                if (!$event->isMainRequest()) {
                    return;
                }
                $request = $event->getRequest();
                
                foreach ($firewalls as $firewallName => $firewallConfig) {
                    $pattern = $firewallConfig['pattern'];
                    if (!$this->requestMatchesPattern($request, $pattern)) {
                        continue;
                    }
                    
                    // First matching firewall — iterate its policies
                    foreach ($firewallConfig as $policyName => $policyOptions) {
                        if (!isset($policies[$policyName])) {
                            continue;
                        }
                        /** @var AuthenticationPolicyInterface $policy */
                        $policy = $policies[$policyName];
                        $options = is_array($policyOptions) ? $policyOptions : [];
                        
                        $authenticator = $policy->getAuthenticator($kernel, $firewallName, $options);
                        
                        try {
                            if (!$authenticator->supports($request)) {
                                continue;
                            }
                            $passport = $authenticator->authenticate($request);
                            $token = $authenticator->createToken($passport, $firewallName);
                            $tokenStorage->setToken($token);
                        } catch (AuthenticationException $e) {
                            // Authentication failed: don't set token, let request continue.
                            // Access rule listener will decide whether to deny.
                        }
                    }
                    break; // First matching firewall takes effect
                }
            },
            MicroKernel::BEFORE_PRIORITY_FIREWALL
        );
    }
    
    /**
     * Register access rule event listener.
     *
     * Runs at priority BEFORE_PRIORITY_FIREWALL - 1 (= 7), after the firewall
     * listener. Iterates access rules in registration order; the first matching
     * rule takes effect. If the rule requires roles and the token is missing or
     * lacks the required roles, throws AccessDeniedHttpException.
     */
    protected function registerAccessRuleListener(
        MicroKernel $kernel,
        TokenStorageInterface $tokenStorage,
        AccessDecisionManagerInterface $accessDecisionManager
    ): void {
        $accessRules = $this->getAccessRules();
        
        $kernel->getContainer()->get('event_dispatcher')->addListener(
            KernelEvents::REQUEST,
            function (RequestEvent $event) use ($accessRules, $tokenStorage, $accessDecisionManager) {
                if (!$event->isMainRequest()) {
                    return;
                }
                $request = $event->getRequest();
                $token = $tokenStorage->getToken();
                
                foreach ($accessRules as [$pattern, $roles, $channel]) {
                    if (!$this->requestMatchesPattern($request, $pattern)) {
                        continue;
                    }
                    
                    // First matching rule takes effect
                    if (empty($roles)) {
                        return; // No role requirement — allow access
                    }
                    
                    if (!$token || !$token->getUser()) {
                        throw new AccessDeniedHttpException('Access Denied');
                    }
                    
                    if (!$accessDecisionManager->decide($token, (array)$roles)) {
                        throw new AccessDeniedHttpException('Access Denied');
                    }
                    
                    return; // Authorization passed
                }
            },
            MicroKernel::BEFORE_PRIORITY_FIREWALL - 1
        );
    }
    
    /**
     * Check whether a request matches a firewall/access-rule pattern.
     *
     * @param Request $request
     * @param mixed   $pattern string (regex) or RequestMatcherInterface
     *
     * @return bool
     */
    protected function requestMatchesPattern(Request $request, mixed $pattern): bool
    {
        if ($pattern instanceof RequestMatcherInterface) {
            return $pattern->matches($request);
        }
        
        return (bool)preg_match('{' . $pattern . '}', rawurldecode($request->getPathInfo()));
    }
}
