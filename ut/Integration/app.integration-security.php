<?php
/**
 * MicroKernel configuration for Security_Authentication_Flow integration tests.
 *
 * Provides a complete Policy → Firewall → AccessRule → Role Hierarchy chain
 * for testing the full authentication/authorization flow via WebTestCase.
 *
 * Note: Security tests are expected to fail in Phase 1 (except NullEntryPointTest).
 * The authenticator system rewrite is deferred to Phase 3.
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAccessRule;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config = [
    'cache_dir'      => isset($cacheDir) ? $cacheDir : __DIR__ . '/../cache',
    'routing'        => [
        'path'       => __DIR__ . '/integration.routes.yml',
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Integration\\',
        ],
    ],
    'view_handlers'  => [new JsonViewHandler()],
    'error_handlers' => [new JsonErrorHandler()],
    'security'       => [
        'policies'       => [
            'mauth' => new TestAuthenticationPolicy(),
        ],
        'firewalls'      => [
            'integration.secured' => new SimpleFirewall(
                [
                    'pattern'  => '^/integration/secured',
                    'policies' => [
                        'mauth' => true,
                    ],
                    'users'    => new TestApiUserProvider(),
                ]
            ),
        ],
        'access_rules'   => [
            ['pattern' => '^/integration/secured/admin', 'roles' => 'ROLE_ADMIN'],
            ['pattern' => '^/integration/secured/parent', 'roles' => 'ROLE_PARENT'],
            ['pattern' => '^/integration/secured/child', 'roles' => 'ROLE_CHILD'],
            ['pattern' => '^/integration/secured', 'roles' => 'ROLE_USER'],
        ],
        'role_hierarchy' => [
            'ROLE_CHILD'  => ['ROLE_USER'],
            'ROLE_PARENT' => ['ROLE_CHILD', 'ROLE_USER'],
            'ROLE_ADMIN'  => ['ROLE_USER'],
        ],
    ],
];

$app = new MicroKernel($config, true);

return $app;
