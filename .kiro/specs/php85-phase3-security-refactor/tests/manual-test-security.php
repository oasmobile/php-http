<?php
/**
 * Manual test helper for Task 9: Security Component 手工测试.
 *
 * Boots MicroKernel with the integration security bootstrap and exercises
 * the full authentication/authorization chain via HTTP requests.
 *
 * Output format: TEST:<id>:<PASS|FAIL detail>
 *
 * Sub-tasks covered:
 *   9.1 — 认证流程端到端行为 (R9.1, R9.2, R9.3)
 *   9.2 — 防火墙和授权行为 (R10.1, R10.2, R10.3, R10.4)
 *   9.3 — 旧类废弃标记 (R7.1, R7.2)
 */

$projectRoot = $argv[1] ?? getcwd();
require_once $projectRoot . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Create a fresh MicroKernel instance from the integration security bootstrap.
 */
function createApp(string $projectRoot): \Oasis\Mlib\Http\MicroKernel
{
    $cacheDir = $projectRoot . '/ut/cache/manual-test-' . uniqid();
    $app = require $projectRoot . '/ut/Integration/app.integration-security.php';
    return $app;
}

/**
 * Send a request through the kernel and return the Response.
 */
function sendRequest(\Oasis\Mlib\Http\MicroKernel $app, string $method, string $uri, array $query = []): Response
{
    $request = Request::create($uri, $method, $query);
    return $app->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
}

// =====================================================================
// 9.1 验证认证流程端到端行为
// =====================================================================

// 9.1.1: Valid credentials (sig=abcd) → 200 + authenticated token in storage
try {
    $app = createApp($projectRoot);
    $response = sendRequest($app, 'GET', '/integration/secured/admin', ['sig' => 'abcd']);
    $status = $response->getStatusCode();
    $token = $app->getToken();
    $user = $app->getUser();

    $errors = [];
    if ($status !== 200) {
        $errors[] = "expected HTTP 200, got $status";
    }
    if ($token === null) {
        $errors[] = "token storage is null (expected authenticated token)";
    }
    if ($user === null) {
        $errors[] = "user is null (expected authenticated user)";
    } elseif ($user->getUserIdentifier() !== 'admin') {
        $errors[] = "expected user 'admin', got '" . $user->getUserIdentifier() . "'";
    }

    $json = json_decode($response->getContent(), true);
    if (!is_array($json) || !isset($json['admin']) || $json['admin'] !== true) {
        $errors[] = "response JSON does not confirm admin access";
    }

    if (empty($errors)) {
        echo "TEST:9.1.1:PASS\n";
    } else {
        echo "TEST:9.1.1:FAIL " . implode('; ', $errors) . "\n";
    }
    $app->shutdown();
} catch (\Throwable $e) {
    echo "TEST:9.1.1:FAIL exception: " . $e->getMessage() . "\n";
}

// 9.1.2: Invalid credentials (sig=invalid) → request not blocked, access rule → 403
try {
    $app = createApp($projectRoot);
    $response = sendRequest($app, 'GET', '/integration/secured/user', ['sig' => 'invalid']);
    $status = $response->getStatusCode();

    if ($status === 403) {
        echo "TEST:9.1.2:PASS\n";
    } else {
        echo "TEST:9.1.2:FAIL expected HTTP 403, got $status\n";
    }
    $app->shutdown();
} catch (\Throwable $e) {
    echo "TEST:9.1.2:FAIL exception: " . $e->getMessage() . "\n";
}

// 9.1.3: No credentials → supports() returns false, skip authentication → 403 from access rule
try {
    $app = createApp($projectRoot);
    $response = sendRequest($app, 'GET', '/integration/secured/user');
    $status = $response->getStatusCode();
    $token = $app->getToken();

    $errors = [];
    if ($status !== 403) {
        $errors[] = "expected HTTP 403, got $status";
    }
    // Token should be null since authenticator skipped (supports() returned false)
    if ($token !== null) {
        $errors[] = "expected null token (authenticator should have skipped), got token with user: " . ($token->getUser() ? $token->getUser()->getUserIdentifier() : 'null');
    }

    if (empty($errors)) {
        echo "TEST:9.1.3:PASS\n";
    } else {
        echo "TEST:9.1.3:FAIL " . implode('; ', $errors) . "\n";
    }
    $app->shutdown();
} catch (\Throwable $e) {
    echo "TEST:9.1.3:FAIL exception: " . $e->getMessage() . "\n";
}

// =====================================================================
// 9.2 验证防火墙和授权行为
// =====================================================================

// 9.2.1: URL matches firewall pattern → triggers authentication
// Verified by: sig=abcd on /integration/secured/user → 200 with authenticated user
try {
    $app = createApp($projectRoot);
    $response = sendRequest($app, 'GET', '/integration/secured/user', ['sig' => 'abcd']);
    $status = $response->getStatusCode();
    $user = $app->getUser();

    $errors = [];
    if ($status !== 200) {
        $errors[] = "expected HTTP 200, got $status";
    }
    if ($user === null || $user->getUserIdentifier() !== 'admin') {
        $errors[] = "expected authenticated user 'admin'";
    }

    if (empty($errors)) {
        echo "TEST:9.2.1:PASS\n";
    } else {
        echo "TEST:9.2.1:FAIL " . implode('; ', $errors) . "\n";
    }
    $app->shutdown();
} catch (\Throwable $e) {
    echo "TEST:9.2.1:FAIL exception: " . $e->getMessage() . "\n";
}

// 9.2.2: URL does not match any firewall pattern → skip authentication
// /integration/public is not under /integration/secured, so no firewall matches
try {
    $app = createApp($projectRoot);
    $response = sendRequest($app, 'GET', '/integration/public', ['sig' => 'abcd']);
    $status = $response->getStatusCode();
    $token = $app->getToken();

    $errors = [];
    if ($status !== 200) {
        $errors[] = "expected HTTP 200, got $status (public route should be accessible)";
    }
    // Token should be null since no firewall matched this URL
    if ($token !== null) {
        $errors[] = "expected null token (no firewall should match /integration/public), got token";
    }

    if (empty($errors)) {
        echo "TEST:9.2.2:PASS\n";
    } else {
        echo "TEST:9.2.2:FAIL " . implode('; ', $errors) . "\n";
    }
    $app->shutdown();
} catch (\Throwable $e) {
    echo "TEST:9.2.2:FAIL exception: " . $e->getMessage() . "\n";
}

// 9.2.3: Access rules match in registration order, first match wins
// Access rules order:
//   1. ^/integration/secured/admin → ROLE_ADMIN
//   2. ^/integration/secured/parent → ROLE_PARENT
//   3. ^/integration/secured/child → ROLE_CHILD
//   4. ^/integration/secured → ROLE_USER
//
// Test: child user (ROLE_CHILD, inherits ROLE_USER) accessing /integration/secured/admin
// The first matching rule requires ROLE_ADMIN → child doesn't have it → 403
// If order were wrong and the last rule (ROLE_USER) matched first, child would get 200
try {
    $app = createApp($projectRoot);
    $response = sendRequest($app, 'GET', '/integration/secured/admin', ['sig' => 'child']);
    $statusAdmin = $response->getStatusCode();
    $app->shutdown();

    // Also verify child CAN access /integration/secured/user (matches 4th rule: ROLE_USER)
    $app2 = createApp($projectRoot);
    $response2 = sendRequest($app2, 'GET', '/integration/secured/user', ['sig' => 'child']);
    $statusUser = $response2->getStatusCode();
    $app2->shutdown();

    $errors = [];
    if ($statusAdmin !== 403) {
        $errors[] = "child accessing /admin: expected 403, got $statusAdmin (first-match rule should require ROLE_ADMIN)";
    }
    if ($statusUser !== 200) {
        $errors[] = "child accessing /user: expected 200, got $statusUser (4th rule ROLE_USER should match)";
    }

    if (empty($errors)) {
        echo "TEST:9.2.3:PASS\n";
    } else {
        echo "TEST:9.2.3:FAIL " . implode('; ', $errors) . "\n";
    }
} catch (\Throwable $e) {
    echo "TEST:9.2.3:FAIL exception: " . $e->getMessage() . "\n";
}

// 9.2.4: Role hierarchy inheritance
// Config: ROLE_ADMIN → [ROLE_USER], ROLE_PARENT → [ROLE_CHILD, ROLE_USER], ROLE_CHILD → [ROLE_USER]
// Test: admin (ROLE_ADMIN) can access /integration/secured/user (requires ROLE_USER) via inheritance
// Test: parent (ROLE_PARENT) can access /integration/secured/child (requires ROLE_CHILD) via inheritance
try {
    // Admin inherits ROLE_USER
    $app = createApp($projectRoot);
    $response = sendRequest($app, 'GET', '/integration/secured/user', ['sig' => 'abcd']);
    $statusAdminUser = $response->getStatusCode();
    $app->shutdown();

    // Parent inherits ROLE_CHILD
    $app2 = createApp($projectRoot);
    $response2 = sendRequest($app2, 'GET', '/integration/secured/child', ['sig' => 'parent']);
    $statusParentChild = $response2->getStatusCode();
    $app2->shutdown();

    // Child inherits ROLE_USER
    $app3 = createApp($projectRoot);
    $response3 = sendRequest($app3, 'GET', '/integration/secured/user', ['sig' => 'child']);
    $statusChildUser = $response3->getStatusCode();
    $app3->shutdown();

    $errors = [];
    if ($statusAdminUser !== 200) {
        $errors[] = "admin → ROLE_USER route: expected 200, got $statusAdminUser";
    }
    if ($statusParentChild !== 200) {
        $errors[] = "parent → ROLE_CHILD route: expected 200, got $statusParentChild";
    }
    if ($statusChildUser !== 200) {
        $errors[] = "child → ROLE_USER route: expected 200, got $statusChildUser";
    }

    if (empty($errors)) {
        echo "TEST:9.2.4:PASS\n";
    } else {
        echo "TEST:9.2.4:FAIL " . implode('; ', $errors) . "\n";
    }
} catch (\Throwable $e) {
    echo "TEST:9.2.4:FAIL exception: " . $e->getMessage() . "\n";
}

// =====================================================================
// 9.3 验证旧类废弃标记
// =====================================================================

// 9.3.1: @deprecated annotation exists on AbstractSimplePreAuthenticator
try {
    $rc = new ReflectionClass(\Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator::class);
    $docComment = $rc->getDocComment();

    if ($docComment !== false && str_contains($docComment, '@deprecated')) {
        // Also verify it points to AbstractPreAuthenticator
        if (str_contains($docComment, 'AbstractPreAuthenticator')) {
            echo "TEST:9.3.1:PASS\n";
        } else {
            echo "TEST:9.3.1:FAIL @deprecated exists but does not reference AbstractPreAuthenticator\n";
        }
    } else {
        echo "TEST:9.3.1:FAIL @deprecated annotation not found on class docblock\n";
    }
} catch (\Throwable $e) {
    echo "TEST:9.3.1:FAIL exception: " . $e->getMessage() . "\n";
}

// 9.3.2: createToken() throws LogicException
try {
    // Create a concrete subclass to test the deprecated methods
    $stub = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
        public function getCredentialsFromRequest(\Symfony\Component\HttpFoundation\Request $request): mixed
        {
            return 'test';
        }
    };

    $threw = false;
    try {
        $stub->createToken(Request::create('/test'), 'main');
    } catch (\LogicException $e) {
        $threw = true;
        // Verify the message mentions AbstractPreAuthenticator
        if (!str_contains($e->getMessage(), 'AbstractPreAuthenticator')) {
            echo "TEST:9.3.2:FAIL LogicException thrown but message does not mention AbstractPreAuthenticator\n";
            $threw = false; // treat as fail
        }
    }

    if ($threw) {
        echo "TEST:9.3.2:PASS\n";
    } else {
        echo "TEST:9.3.2:FAIL createToken() did not throw LogicException\n";
    }
} catch (\Throwable $e) {
    echo "TEST:9.3.2:FAIL exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

// 9.3.3: authenticateToken() throws LogicException
try {
    $stub = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
        public function getCredentialsFromRequest(\Symfony\Component\HttpFoundation\Request $request): mixed
        {
            return 'test';
        }
    };

    $threw = false;
    try {
        $mockToken = new \Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken(
            new \Oasis\Mlib\Http\Test\Helpers\Security\TestApiUser('test', ['ROLE_USER']),
            'main',
            ['ROLE_USER']
        );
        $mockProvider = new \Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider();
        $stub->authenticateToken($mockToken, $mockProvider, 'main');
    } catch (\LogicException $e) {
        $threw = true;
    }

    if ($threw) {
        echo "TEST:9.3.3:PASS\n";
    } else {
        echo "TEST:9.3.3:FAIL authenticateToken() did not throw LogicException\n";
    }
} catch (\Throwable $e) {
    echo "TEST:9.3.3:FAIL exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
}

// 9.3.4: supportsToken() throws LogicException
try {
    $stub = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
        public function getCredentialsFromRequest(\Symfony\Component\HttpFoundation\Request $request): mixed
        {
            return 'test';
        }
    };

    $threw = false;
    try {
        $mockToken = new \Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken(
            new \Oasis\Mlib\Http\Test\Helpers\Security\TestApiUser('test', ['ROLE_USER']),
            'main',
            ['ROLE_USER']
        );
        $stub->supportsToken($mockToken, 'main');
    } catch (\LogicException $e) {
        $threw = true;
    }

    if ($threw) {
        echo "TEST:9.3.4:PASS\n";
    } else {
        echo "TEST:9.3.4:FAIL supportsToken() did not throw LogicException\n";
    }
} catch (\Throwable $e) {
    echo "TEST:9.3.4:FAIL exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
}
