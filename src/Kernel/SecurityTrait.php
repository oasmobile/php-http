<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Kernel;

use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Utils\DataType;

/**
 * Security config injection API extracted from MicroKernel.
 *
 * Provides pre-boot security config injection (batch and fine-grained),
 * merging Constructor_Config with Pending_Queue at boot time.
 *
 * Pattern: mirrors RoutingTrait (pending queue + boot-time merge).
 */
trait SecurityTrait
{
    /**
     * Batch inject security config before boot.
     *
     * Accepts a config array with top-level keys: firewalls, access_rules, policies, role_hierarchy.
     * Unknown keys are rejected. Conflicts are detected immediately (fail-fast) unless $allowOverwrite is true.
     *
     * @param array<string, mixed> $config Security config fragment
     * @param bool $allowOverwrite If true, same-name entries overwrite instead of throwing
     * @throws \LogicException If called after boot
     * @throws \InvalidArgumentException If unknown top-level keys are present
     * @throws \LogicException If duplicate firewall/policy/role detected and $allowOverwrite is false
     */
    public function addSecurityConfig(array $config, bool $allowOverwrite = false): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }

        $allowedKeys = ['firewalls', 'access_rules', 'policies', 'role_hierarchy'];
        $unknownKeys = array_diff(array_keys($config), $allowedKeys);
        if ($unknownKeys) {
            throw new \InvalidArgumentException(
                'Unknown security config keys: ' . implode(', ', $unknownKeys)
            );
        }

        // Fail-fast: check conflicts immediately against current accumulated state
        $this->validateSecurityConfigConflicts($config, $allowOverwrite);

        $this->pendingSecurityConfigs[] = ['config' => $config, 'allowOverwrite' => $allowOverwrite];
    }

    /**
     * Inject a single firewall config before boot.
     *
     * @param string $name Firewall name (must be unique unless $allowOverwrite is true)
     * @param array<string, mixed> $config Firewall configuration
     * @param bool $allowOverwrite If true, same-name firewall overwrites instead of throwing
     * @throws \LogicException If called after boot
     * @throws \LogicException If duplicate firewall detected and $allowOverwrite is false
     */
    public function addFirewall(string $name, array $config, bool $allowOverwrite = false): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }

        $this->validateSecurityConfigConflicts(['firewalls' => [$name => $config]], $allowOverwrite);
        $this->pendingSecurityConfigs[] = ['config' => ['firewalls' => [$name => $config]], 'allowOverwrite' => $allowOverwrite];
    }

    /**
     * Inject a single access rule before boot.
     *
     * Access rules are always appended in registration order (no conflict concept).
     *
     * @param array<string, mixed> $rule Access rule configuration
     * @throws \LogicException If called after boot
     */
    public function addAccessRule(array $rule): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }

        $this->pendingSecurityConfigs[] = ['config' => ['access_rules' => [$rule]], 'allowOverwrite' => false];
    }

    /**
     * Inject a single policy config before boot.
     *
     * @param string $name Policy name (must be unique unless $allowOverwrite is true)
     * @param mixed $config Policy configuration
     * @param bool $allowOverwrite If true, same-name policy overwrites instead of throwing
     * @throws \LogicException If called after boot
     * @throws \LogicException If duplicate policy detected and $allowOverwrite is false
     */
    public function addPolicy(string $name, mixed $config, bool $allowOverwrite = false): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }

        $this->validateSecurityConfigConflicts(['policies' => [$name => $config]], $allowOverwrite);
        $this->pendingSecurityConfigs[] = ['config' => ['policies' => [$name => $config]], 'allowOverwrite' => $allowOverwrite];
    }

    /**
     * Inject a single role hierarchy mapping before boot.
     *
     * @param string $role Role name (must be unique unless $allowOverwrite is true)
     * @param array<string> $children Child roles
     * @param bool $allowOverwrite If true, same-role entry overwrites instead of throwing
     * @throws \LogicException If called after boot
     * @throws \LogicException If duplicate role detected and $allowOverwrite is false
     */
    public function addRoleHierarchy(string $role, array $children, bool $allowOverwrite = false): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add security config after the kernel has been booted.');
        }

        $this->validateSecurityConfigConflicts(['role_hierarchy' => [$role => $children]], $allowOverwrite);
        $this->pendingSecurityConfigs[] = ['config' => ['role_hierarchy' => [$role => $children]], 'allowOverwrite' => $allowOverwrite];
    }

    /**
     * Read-only query: return the current accumulated security config.
     *
     * Returns Constructor_Config + Pending_Queue merged view.
     * Throws LogicException after boot (consistent with injection APIs).
     *
     * @return array<string, mixed> Merged security config
     * @throws \LogicException If called after boot
     */
    public function getSecurityConfig(): array
    {
        if ($this->booted) {
            throw new \LogicException('Cannot query security config after the kernel has been booted.');
        }

        return $this->getSecurityConfigInternal();
    }

    /**
     * Merge Constructor_Config with Pending_Queue and initialize SimpleSecurityProvider.
     *
     * Replaces the original registerSecurity() from ServicesTrait.
     */
    protected function registerSecurity(): void
    {
        $constructorConfig = $this->httpDataProvider->getOptional('security', DataType::Mixed);
        $base = is_array($constructorConfig) ? $constructorConfig : [];

        $mergedConfig = $this->mergeSecurityConfigs($base, $this->pendingSecurityConfigs);

        if (empty($mergedConfig)) {
            return;
        }

        $securityProvider = new SimpleSecurityProvider();
        $securityProvider->register($this, $mergedConfig);
    }

    /**
     * Fail-fast conflict detection for security config injection.
     *
     * Checks the incoming fragment against the current accumulated state
     * (Constructor_Config + all previously queued entries). Throws on duplicate
     * firewall/policy/role unless $allowOverwrite is true.
     *
     * @param array<string, mixed> $fragment The config fragment being injected
     * @param bool $allowOverwrite If true, skip conflict detection
     * @throws \LogicException On duplicate firewall, policy, or role_hierarchy entry
     */
    private function validateSecurityConfigConflicts(array $fragment, bool $allowOverwrite): void
    {
        if ($allowOverwrite) {
            return; // overwrite mode skips conflict detection
        }

        // Build current accumulated state for conflict checking (Constructor_Config + Pending_Queue)
        $currentConfig = $this->getSecurityConfigInternal();

        if (isset($fragment['firewalls'])) {
            $existingFirewalls = $currentConfig['firewalls'] ?? [];
            foreach ($fragment['firewalls'] as $name => $fw) {
                if (array_key_exists($name, $existingFirewalls)) {
                    // Idempotent: same name + same config is not a conflict
                    if ($existingFirewalls[$name] === $fw) {
                        continue;
                    }
                    throw new \LogicException("Duplicate firewall: '$name'");
                }
            }
        }

        if (isset($fragment['policies'])) {
            $existingPolicies = $currentConfig['policies'] ?? [];
            foreach ($fragment['policies'] as $name => $policy) {
                if (array_key_exists($name, $existingPolicies)) {
                    // Idempotent: same name + same config is not a conflict
                    if ($existingPolicies[$name] === $policy) {
                        continue;
                    }
                    throw new \LogicException("Duplicate policy: '$name'");
                }
            }
        }

        if (isset($fragment['role_hierarchy'])) {
            $existingRoles = $currentConfig['role_hierarchy'] ?? [];
            foreach ($fragment['role_hierarchy'] as $role => $children) {
                if (array_key_exists($role, $existingRoles)) {
                    // Idempotent: same role + same children is not a conflict
                    if ($existingRoles[$role] === $children) {
                        continue;
                    }
                    throw new \LogicException("Duplicate role in role_hierarchy: '$role'");
                }
            }
        }
    }

    /**
     * Build the current accumulated security config (Constructor_Config + Pending_Queue).
     *
     * Used internally for conflict detection and getSecurityConfig().
     *
     * @return array<string, mixed>
     */
    private function getSecurityConfigInternal(): array
    {
        $constructorConfig = $this->httpDataProvider->getOptional('security', DataType::Mixed);
        $base = is_array($constructorConfig) ? $constructorConfig : [];

        return $this->mergeSecurityConfigs($base, $this->pendingSecurityConfigs);
    }

    /**
     * Merge base config with pending queue entries.
     *
     * - firewalls: same-name throws LogicException unless allowOverwrite
     * - policies: same-name throws LogicException unless allowOverwrite
     * - role_hierarchy: same-role throws LogicException unless allowOverwrite
     * - access_rules: always appended in registration order
     *
     * @param array<string, mixed> $base Constructor_Config (or empty)
     * @param list<array{config: array<string, mixed>, allowOverwrite: bool}> $pendingQueue
     * @return array<string, mixed> Merged config (empty keys removed)
     */
    private function mergeSecurityConfigs(array $base, array $pendingQueue): array
    {
        /** @var array<string, mixed> $firewalls */
        $firewalls = $base['firewalls'] ?? [];
        /** @var list<mixed> $accessRules */
        $accessRules = $base['access_rules'] ?? [];
        /** @var array<string, mixed> $policies */
        $policies = $base['policies'] ?? [];
        /** @var array<string, mixed> $roleHierarchy */
        $roleHierarchy = $base['role_hierarchy'] ?? [];

        foreach ($pendingQueue as $entry) {
            $fragment = $entry['config'];
            $overwrite = $entry['allowOverwrite'];

            // firewalls: same-name throws exception unless allowOverwrite or idempotent
            if (isset($fragment['firewalls'])) {
                foreach ($fragment['firewalls'] as $name => $fw) {
                    if (!$overwrite && array_key_exists($name, $firewalls)) {
                        // Idempotent: same name + same config is not a conflict
                        if ($firewalls[$name] === $fw) {
                            continue;
                        }
                        throw new \LogicException("Duplicate firewall: '$name'");
                    }
                    $firewalls[$name] = $fw;
                }
            }

            // policies: same-name throws exception unless allowOverwrite or idempotent
            if (isset($fragment['policies'])) {
                foreach ($fragment['policies'] as $name => $policy) {
                    if (!$overwrite && array_key_exists($name, $policies)) {
                        // Idempotent: same name + same config is not a conflict
                        if ($policies[$name] === $policy) {
                            continue;
                        }
                        throw new \LogicException("Duplicate policy: '$name'");
                    }
                    $policies[$name] = $policy;
                }
            }

            // role_hierarchy: same-role throws exception unless allowOverwrite or idempotent
            if (isset($fragment['role_hierarchy'])) {
                foreach ($fragment['role_hierarchy'] as $role => $children) {
                    if (!$overwrite && array_key_exists($role, $roleHierarchy)) {
                        // Idempotent: same role + same children is not a conflict
                        if ($roleHierarchy[$role] === $children) {
                            continue;
                        }
                        throw new \LogicException("Duplicate role in role_hierarchy: '$role'");
                    }
                    $roleHierarchy[$role] = $children;
                }
            }

            // access_rules: always append in order
            if (isset($fragment['access_rules'])) {
                foreach ($fragment['access_rules'] as $rule) {
                    $accessRules[] = $rule;
                }
            }
        }

        // Remove empty keys to match original behavior (no security if all empty)
        $merged = [];
        if (!empty($firewalls)) {
            $merged['firewalls'] = $firewalls;
        }
        if (!empty($accessRules)) {
            $merged['access_rules'] = $accessRules;
        }
        if (!empty($policies)) {
            $merged['policies'] = $policies;
        }
        if (!empty($roleHierarchy)) {
            $merged['role_hierarchy'] = $roleHierarchy;
        }

        return $merged;
    }
}
