<?php
declare(strict_types=1);

/**
 * Manual Test Script — Task 7: 手工测试
 *
 * Covers:
 *   7.1 验证 ServiceProvider 注入 security config 的完整流程
 *   7.2 验证冲突检测的用户体验
 *   7.3 验证 boot 后调用保护
 *   7.4 验证向后兼容性
 *
 * Usage: php .kiro/specs/hotfix-3.7.0/tests/test-task-7.php
 *
 * Exit code 0 = all PASS, non-zero = at least one FAIL.
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Request;

// ─── Helpers ─────────────────────────────────────────────────────────

$results = [];
$failures = 0;

function pass(string $id, string $desc): void
{
    global $results;
    $results[] = ['status' => 'PASS', 'id' => $id, 'desc' => $desc];
    echo "  ✅ PASS: [$id] $desc\n";
}

function fail(string $id, string $desc, string $reason): void
{
    global $results, $failures;
    $results[] = ['status' => 'FAIL', 'id' => $id, 'desc' => $desc, 'reason' => $reason];
    $failures++;
    echo "  ❌ FAIL: [$id] $desc\n     Reason: $reason\n";
}

function createCacheDir(): string
{
    $dir = sys_get_temp_dir() . '/oasis_http_manual_test_' . uniqid();
    @mkdir($dir, 0777, true);
    return $dir;
}

function buildKernel(array $config): MicroKernel
{
    return new MicroKernel($config, false);
}

function createBaseConfig(): array
{
    return [
        'cache_dir'     => createCacheDir(),
        'routing'       => [
            'path'       => __DIR__ . '/../../../../tests/Integration/integration.routes.yml',
            'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Integration\\'],
        ],
        'view_handlers' => [new JsonViewHandler()],
    ];
}

// ═══════════════════════════════════════════════════════════════════════
echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Manual Test — Task 7: Security Injection API Verification  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// ─── 7.1 验证 ServiceProvider 注入 security config 的完整流程 ────────
echo "━━━ 7.1 验证 ServiceProvider 注入 security config 的完整流程 ━━━\n\n";

try {
    $config = createBaseConfig();
    $kernel = buildKernel($config);

    // Simulate ServiceProvider register() phase
    $kernel->addSecurityConfig([
        'policies' => [
            'mauth' => new TestAuthenticationPolicy(),
        ],
        'firewalls' => [
            'integration.secured' => new SimpleFirewall([
                'pattern'  => '^/integration/secured',
                'policies' => ['mauth' => true],
                'users'    => new TestApiUserProvider(),
            ]),
        ],
        'access_rules' => [
            ['pattern' => '^/integration/secured/admin', 'roles' => 'ROLE_ADMIN'],
            ['pattern' => '^/integration/secured', 'roles' => 'ROLE_USER'],
        ],
        'role_hierarchy' => [
            'ROLE_ADMIN' => ['ROLE_USER'],
        ],
    ]);

    // Boot and handle request with valid credentials
    $kernel->boot();
    $request = Request::create('/integration/secured/admin', 'GET', ['sig' => 'abcd']);
    $response = $kernel->handle($request);

    // Check 1: Response is 200 (not 403)
    if ($response->getStatusCode() === 200) {
        pass('7.1.1', 'Secured route returns 200 (not 403) after ServiceProvider injection');
    } else {
        fail('7.1.1', 'Secured route returns 200', "Got HTTP {$response->getStatusCode()}");
    }

    // Check 2: Firewall listener is registered (response has valid user data)
    $data = json_decode($response->getContent(), true);
    if (is_array($data) && isset($data['user']) && $data['user'] === 'admin') {
        pass('7.1.2', 'Firewall listener registered — user authenticated as admin');
    } else {
        fail('7.1.2', 'Firewall listener registered', 'Response data: ' . $response->getContent());
    }

    // Check 3: tokenStorage has token
    $token = $kernel->getToken();
    if ($token !== null) {
        pass('7.1.3', 'tokenStorage contains token after boot');
    } else {
        fail('7.1.3', 'tokenStorage contains token', 'getToken() returned null');
    }

    // Check 4: User from token matches
    $user = $kernel->getUser();
    if ($user !== null && $user->getUserIdentifier() === 'admin') {
        pass('7.1.4', 'Token user identifier is "admin"');
    } else {
        $id = $user ? $user->getUserIdentifier() : 'null';
        fail('7.1.4', 'Token user identifier is "admin"', "Got: $id");
    }

    $kernel->shutdown();
} catch (\Throwable $e) {
    fail('7.1', 'ServiceProvider injection flow', get_class($e) . ': ' . $e->getMessage());
}

// ─── 7.2 验证冲突检测的用户体验 ─────────────────────────────────────
echo "\n━━━ 7.2 验证冲突检测的用户体验 ━━━\n\n";

// 7.2.1: Two providers inject same-name firewall with $allowOverwrite = false → LogicException
try {
    $config = createBaseConfig();
    $kernel = buildKernel($config);

    $kernel->addFirewall('api', [
        'pattern'  => '^/api',
        'policies' => ['mauth' => true],
        'users'    => new TestApiUserProvider(),
    ]);

    // Second provider injects same-name firewall with different config
    try {
        $kernel->addFirewall('api', [
            'pattern'  => '^/api/v2',
            'policies' => ['mauth' => true],
            'users'    => new TestApiUserProvider(),
        ]);
        fail('7.2.1', 'Duplicate firewall throws LogicException', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Duplicate firewall') && str_contains($e->getMessage(), 'api')) {
            pass('7.2.1', "Duplicate firewall throws LogicException: \"{$e->getMessage()}\"");
        } else {
            fail('7.2.1', 'Clear error message', "Message: {$e->getMessage()}");
        }
    }

    $kernel->shutdown();
} catch (\Throwable $e) {
    fail('7.2.1', 'Conflict detection', get_class($e) . ': ' . $e->getMessage());
}

// 7.2.2: Same-name firewall with $allowOverwrite = true → silent overwrite
try {
    $config = createBaseConfig();
    $kernel = buildKernel($config);

    $kernel->addFirewall('api', [
        'pattern'  => '^/api',
        'policies' => ['mauth' => true],
        'users'    => new TestApiUserProvider(),
    ]);

    // Second provider overwrites with allowOverwrite = true
    $kernel->addFirewall('api', [
        'pattern'  => '^/api/v2',
        'policies' => ['mauth' => true],
        'users'    => new TestApiUserProvider(),
    ], true);

    // Verify the overwrite took effect
    $secConfig = $kernel->getSecurityConfig();
    if (isset($secConfig['firewalls']['api']) && $secConfig['firewalls']['api']['pattern'] === '^/api/v2') {
        pass('7.2.2', 'allowOverwrite=true silently overwrites — pattern updated to ^/api/v2');
    } else {
        fail('7.2.2', 'Silent overwrite', 'Firewall config not updated');
    }

    $kernel->shutdown();
} catch (\Throwable $e) {
    fail('7.2.2', 'allowOverwrite=true', get_class($e) . ': ' . $e->getMessage());
}

// 7.2.3: Duplicate policy conflict
try {
    $config = createBaseConfig();
    $kernel = buildKernel($config);

    $kernel->addPolicy('mauth', new TestAuthenticationPolicy());

    try {
        // Different config for same policy name
        $kernel->addPolicy('mauth', ['type' => 'different']);
        fail('7.2.3', 'Duplicate policy throws LogicException', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Duplicate policy') && str_contains($e->getMessage(), 'mauth')) {
            pass('7.2.3', "Duplicate policy throws LogicException: \"{$e->getMessage()}\"");
        } else {
            fail('7.2.3', 'Clear error message for policy', "Message: {$e->getMessage()}");
        }
    }

    $kernel->shutdown();
} catch (\Throwable $e) {
    fail('7.2.3', 'Policy conflict detection', get_class($e) . ': ' . $e->getMessage());
}

// 7.2.4: Duplicate role_hierarchy conflict
try {
    $config = createBaseConfig();
    $kernel = buildKernel($config);

    $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_USER']);

    try {
        $kernel->addRoleHierarchy('ROLE_ADMIN', ['ROLE_MANAGER', 'ROLE_USER']);
        fail('7.2.4', 'Duplicate role throws LogicException', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Duplicate role') && str_contains($e->getMessage(), 'ROLE_ADMIN')) {
            pass('7.2.4', "Duplicate role throws LogicException: \"{$e->getMessage()}\"");
        } else {
            fail('7.2.4', 'Clear error message for role', "Message: {$e->getMessage()}");
        }
    }

    $kernel->shutdown();
} catch (\Throwable $e) {
    fail('7.2.4', 'Role conflict detection', get_class($e) . ': ' . $e->getMessage());
}

// ─── 7.3 验证 boot 后调用保护 ───────────────────────────────────────
echo "\n━━━ 7.3 验证 boot 后调用保护 ━━━\n\n";

try {
    $config = createBaseConfig();
    $kernel = buildKernel($config);
    $kernel->boot();

    // 7.3.1: addSecurityConfig after boot
    try {
        $kernel->addSecurityConfig(['firewalls' => ['late' => ['pattern' => '^/late']]]);
        fail('7.3.1', 'addSecurityConfig after boot throws', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Cannot add security config after the kernel has been booted')) {
            pass('7.3.1', "addSecurityConfig after boot: \"{$e->getMessage()}\"");
        } else {
            fail('7.3.1', 'Clear "cannot add after boot" message', "Message: {$e->getMessage()}");
        }
    }

    // 7.3.2: addFirewall after boot
    try {
        $kernel->addFirewall('late', ['pattern' => '^/late']);
        fail('7.3.2', 'addFirewall after boot throws', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Cannot add security config after the kernel has been booted')) {
            pass('7.3.2', "addFirewall after boot: \"{$e->getMessage()}\"");
        } else {
            fail('7.3.2', 'Clear message', "Message: {$e->getMessage()}");
        }
    }

    // 7.3.3: addAccessRule after boot
    try {
        $kernel->addAccessRule(['pattern' => '^/late', 'roles' => 'ROLE_USER']);
        fail('7.3.3', 'addAccessRule after boot throws', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Cannot add security config after the kernel has been booted')) {
            pass('7.3.3', "addAccessRule after boot: \"{$e->getMessage()}\"");
        } else {
            fail('7.3.3', 'Clear message', "Message: {$e->getMessage()}");
        }
    }

    // 7.3.4: addPolicy after boot
    try {
        $kernel->addPolicy('late', new TestAuthenticationPolicy());
        fail('7.3.4', 'addPolicy after boot throws', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Cannot add security config after the kernel has been booted')) {
            pass('7.3.4', "addPolicy after boot: \"{$e->getMessage()}\"");
        } else {
            fail('7.3.4', 'Clear message', "Message: {$e->getMessage()}");
        }
    }

    // 7.3.5: addRoleHierarchy after boot
    try {
        $kernel->addRoleHierarchy('ROLE_LATE', ['ROLE_USER']);
        fail('7.3.5', 'addRoleHierarchy after boot throws', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Cannot add security config after the kernel has been booted')) {
            pass('7.3.5', "addRoleHierarchy after boot: \"{$e->getMessage()}\"");
        } else {
            fail('7.3.5', 'Clear message', "Message: {$e->getMessage()}");
        }
    }

    // 7.3.6: getSecurityConfig after boot
    try {
        $kernel->getSecurityConfig();
        fail('7.3.6', 'getSecurityConfig after boot throws', 'No exception was thrown');
    } catch (\LogicException $e) {
        if (str_contains($e->getMessage(), 'Cannot query security config after the kernel has been booted')) {
            pass('7.3.6', "getSecurityConfig after boot: \"{$e->getMessage()}\"");
        } else {
            fail('7.3.6', 'Clear message', "Message: {$e->getMessage()}");
        }
    }

    $kernel->shutdown();
} catch (\Throwable $e) {
    fail('7.3', 'Boot-after protection', get_class($e) . ': ' . $e->getMessage());
}

// ─── 7.4 验证向后兼容性 ─────────────────────────────────────────────
echo "\n━━━ 7.4 验证向后兼容性 ━━━\n\n";

try {
    // Constructor-only security config (no ServiceProvider injection)
    $config = createBaseConfig();
    $config['security'] = [
        'policies' => [
            'mauth' => new TestAuthenticationPolicy(),
        ],
        'firewalls' => [
            'integration.secured' => new SimpleFirewall([
                'pattern'  => '^/integration/secured',
                'policies' => ['mauth' => true],
                'users'    => new TestApiUserProvider(),
            ]),
        ],
        'access_rules' => [
            ['pattern' => '^/integration/secured/admin', 'roles' => 'ROLE_ADMIN'],
            ['pattern' => '^/integration/secured', 'roles' => 'ROLE_USER'],
        ],
        'role_hierarchy' => [
            'ROLE_ADMIN' => ['ROLE_USER'],
        ],
    ];

    $kernel = buildKernel($config);
    $kernel->boot();

    // 7.4.1: Secured route with valid credentials returns 200
    $request = Request::create('/integration/secured/admin', 'GET', ['sig' => 'abcd']);
    $response = $kernel->handle($request);

    if ($response->getStatusCode() === 200) {
        pass('7.4.1', 'Constructor-only config: secured route returns 200');
    } else {
        fail('7.4.1', 'Constructor-only config returns 200', "Got HTTP {$response->getStatusCode()}");
    }

    // 7.4.2: User is correctly authenticated
    $data = json_decode($response->getContent(), true);
    if (is_array($data) && isset($data['user']) && $data['user'] === 'admin') {
        pass('7.4.2', 'Constructor-only config: user authenticated as admin');
    } else {
        fail('7.4.2', 'User authenticated', 'Response: ' . $response->getContent());
    }

    // 7.4.3: Token storage has token
    $token = $kernel->getToken();
    if ($token !== null) {
        pass('7.4.3', 'Constructor-only config: tokenStorage has token');
    } else {
        fail('7.4.3', 'tokenStorage has token', 'getToken() returned null');
    }

    $kernel->shutdown();

    // 7.4.4: No security config → no security provider initialized (early return)
    $config2 = createBaseConfig();
    $kernel2 = buildKernel($config2);
    $kernel2->boot();

    $request2 = Request::create('/integration/public', 'GET');
    $response2 = $kernel2->handle($request2);

    if ($response2->getStatusCode() === 200) {
        pass('7.4.4', 'No security config: public route returns 200 (no security interference)');
    } else {
        fail('7.4.4', 'Public route returns 200', "Got HTTP {$response2->getStatusCode()}");
    }

    // 7.4.5: Token storage is null when no security config
    $token2 = $kernel2->getToken();
    if ($token2 === null) {
        pass('7.4.5', 'No security config: tokenStorage is null (no provider initialized)');
    } else {
        fail('7.4.5', 'tokenStorage is null', 'getToken() returned non-null');
    }

    $kernel2->shutdown();
} catch (\Throwable $e) {
    fail('7.4', 'Backward compatibility', get_class($e) . ': ' . $e->getMessage());
}

// ─── Summary ─────────────────────────────────────────────────────────
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$total = count($results);
$passed = $total - $failures;
echo "  Total: $total | Passed: $passed | Failed: $failures\n\n";

if ($failures > 0) {
    echo "  FAILED TESTS:\n";
    foreach ($results as $r) {
        if ($r['status'] === 'FAIL') {
            echo "    ❌ [{$r['id']}] {$r['desc']}: {$r['reason']}\n";
        }
    }
    echo "\n";
}

echo $failures === 0 ? "  🎉 ALL TESTS PASSED\n\n" : "  ⚠️  SOME TESTS FAILED\n\n";

exit($failures);
