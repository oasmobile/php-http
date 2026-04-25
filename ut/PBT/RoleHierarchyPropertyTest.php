<?php
/**
 * Property-Based Tests for Role Hierarchy.
 *
 * Feature: php85-phase3-security-refactor
 *
 * Property 10: Role hierarchy merge idempotence
 * Property 11: Role hierarchy 继承链传递性
 * Property 12: Role hierarchy single-level round-trip
 *
 * 使用 Eris 生成随机 role 名称和继承关系验证 hierarchy 行为。
 *
 * Ref: Requirements 13.1, 13.2, 13.3
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class RoleHierarchyPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 10: Role hierarchy merge idempotence ──────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 10: Role hierarchy merge idempotence
     * Repeating addRoleHierarchy() with the same parent→children mapping does not
     * change the semantic result (the set of children for each parent is equivalent).
     *
     * Ref: Requirements 13.1
     */
    public function testRoleHierarchyMergeIdempotence(): void
    {
        $this->forAll(
            // number of parent roles (1–4)
            Generators::choose(1, 4),
            // number of children per parent (1–3)
            Generators::choose(1, 3),
            // number of duplicate additions (2–5)
            Generators::choose(2, 5)
        )->then(function (int $parentCount, int $childrenPerParent, int $repeatCount) {
            // Build a hierarchy mapping
            $hierarchy = [];
            for ($p = 0; $p < $parentCount; $p++) {
                $parent = 'ROLE_PARENT_' . strtoupper(bin2hex(random_bytes(3)));
                $children = [];
                for ($c = 0; $c < $childrenPerParent; $c++) {
                    $children[] = 'ROLE_CHILD_' . strtoupper(bin2hex(random_bytes(3)));
                }
                $hierarchy[$parent] = $children;
            }

            // Provider with single addition
            $provider1 = new SimpleSecurityProvider();
            foreach ($hierarchy as $parent => $children) {
                $provider1->addRoleHierarchy($parent, $children);
            }

            // Provider with repeated additions
            $provider2 = new SimpleSecurityProvider();
            for ($r = 0; $r < $repeatCount; $r++) {
                foreach ($hierarchy as $parent => $children) {
                    $provider2->addRoleHierarchy($parent, $children);
                }
            }

            // Register both to get parsed hierarchy
            $this->registerMinimal($provider1);
            $this->registerMinimal($provider2);

            $h1 = $provider1->getRoleHierarchy();
            $h2 = $provider2->getRoleHierarchy();

            // Semantic equivalence: same parents, same unique children sets
            $this->assertSame(
                array_keys($h1),
                array_keys($h2),
                'Both providers should have the same parent roles'
            );

            foreach ($h1 as $parent => $children1) {
                $children2 = $h2[$parent];
                // Deduplicate and sort for comparison
                $set1 = array_unique($children1);
                $set2 = array_unique($children2);
                sort($set1);
                sort($set2);
                $this->assertSame(
                    $set1,
                    $set2,
                    "Children set for {$parent} should be semantically equivalent after dedup"
                );
            }
        });
    }

    // ─── Property 11: Role hierarchy 继承链传递性 ────────────────────

    /**
     * Feature: php85-phase3-security-refactor, Property 11: Role hierarchy 继承链传递性
     * For a chain A → B → C, resolving role A includes both B and C.
     * Longer chains produce equal or larger resolved role sets.
     *
     * Ref: Requirements 13.2
     */
    public function testRoleHierarchyTransitivity(): void
    {
        $this->forAll(
            // chain length (3–6 roles in the chain)
            Generators::choose(3, 6)
        )->then(function (int $chainLength) {
            // Build a chain: ROLE_0 → ROLE_1 → ROLE_2 → ... → ROLE_(n-1)
            $roles = [];
            for ($i = 0; $i < $chainLength; $i++) {
                $roles[] = 'ROLE_CHAIN_' . $i . '_' . strtoupper(bin2hex(random_bytes(2)));
            }

            $hierarchyMap = [];
            for ($i = 0; $i < $chainLength - 1; $i++) {
                $hierarchyMap[$roles[$i]] = [$roles[$i + 1]];
            }

            // Use Symfony's RoleHierarchy to resolve
            $roleHierarchy = new RoleHierarchy($hierarchyMap);
            $resolved = $roleHierarchy->getReachableRoleNames([$roles[0]]);

            // The resolved set should contain ALL roles in the chain
            foreach ($roles as $role) {
                $this->assertContains(
                    $role,
                    $resolved,
                    "Resolved roles for {$roles[0]} should contain {$role} (transitivity)"
                );
            }

            // Metamorphic: resolving a role deeper in the chain should produce
            // a smaller or equal set
            $midIndex = intdiv($chainLength, 2);
            $resolvedMid = $roleHierarchy->getReachableRoleNames([$roles[$midIndex]]);
            $this->assertLessThanOrEqual(
                count($resolved),
                count($resolvedMid),
                'Resolving a deeper role should produce equal or fewer reachable roles'
            );
        });
    }

    // ─── Property 12: Role hierarchy single-level round-trip ────────

    /**
     * Feature: php85-phase3-security-refactor, Property 12: Role hierarchy single-level round-trip
     * For a single-level hierarchy A → [B, C], getRoleHierarchy() output for A
     * contains all declared children.
     *
     * Ref: Requirements 13.3
     */
    public function testRoleHierarchySingleLevelRoundTrip(): void
    {
        $this->forAll(
            // number of children (1–5)
            Generators::choose(1, 5)
        )->then(function (int $childCount) {
            $parent = 'ROLE_PARENT_' . strtoupper(bin2hex(random_bytes(3)));
            $children = [];
            for ($i = 0; $i < $childCount; $i++) {
                $children[] = 'ROLE_CHILD_' . strtoupper(bin2hex(random_bytes(3)));
            }

            $provider = new SimpleSecurityProvider();
            $provider->addRoleHierarchy($parent, $children);
            $this->registerMinimal($provider);

            $hierarchy = $provider->getRoleHierarchy();

            // round-trip: parent key exists
            $this->assertArrayHasKey(
                $parent,
                $hierarchy,
                "getRoleHierarchy() should contain parent role {$parent}"
            );

            // round-trip: all children are present
            $storedChildren = $hierarchy[$parent];
            foreach ($children as $child) {
                $this->assertContains(
                    $child,
                    $storedChildren,
                    "getRoleHierarchy()[{$parent}] should contain child {$child}"
                );
            }
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Register a SimpleSecurityProvider with minimal config so that
     * getConfigDataProvider() / getRoleHierarchy() can be called.
     */
    private function registerMinimal(SimpleSecurityProvider $provider): void
    {
        $dispatcher = $this->createStub(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class);
        $container = $this->createStub(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('get')->willReturn($dispatcher);

        $kernel = $this->createStub(\Oasis\Mlib\Http\MicroKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        $provider->register($kernel);
    }
}
