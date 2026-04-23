<?php
/**
 * SilexKernel configuration for Security_Authentication_Flow integration tests (R10).
 *
 * Provides a complete Policy → Firewall → AccessRule → Role Hierarchy chain
 * for testing the full authentication/authorization flow via WebTestCase.
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAccessRule;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Test\Security\SessionServiceProvider;
use Oasis\Mlib\Http\Views\JsonViewHandler;

$config = [
    'cache_dir' => __DIR__ . '/../cache',
    'routing'   => [
        'path'       => __DIR__ . '/integration.routes.yml',
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Integration\\',
        ],
    ],
];

$app = new SilexKernel($config, true);

$app->view_handlers  = [new JsonViewHandler()];
$app->error_handlers = [new JsonErrorHandler()];

// --- Security configuration: Policy → Firewall → AccessRule → Role Hierarchy ---

$securityProvider = new SimpleSecurityProvider();

// Custom pre-authentication policy (token-based via ?sig= query parameter)
$secPolicy = new TestAuthenticationPolicy();
$securityProvider->addAuthenticationPolicy('mauth', $secPolicy);

// Firewall: protect all /integration/secured/* routes with pre-auth
$securityProvider->addFirewall(
    'integration.secured',
    new SimpleFirewall(
        [
            'pattern'  => '^/integration/secured',
            'policies' => [
                'mauth' => true,
            ],
            'users'    => new TestApiUserProvider(),
        ]
    )
);

// AccessRule: /integration/secured/admin requires ROLE_ADMIN
$securityProvider->addAccessRule(
    new TestAccessRule('^/integration/secured/admin', 'ROLE_ADMIN')
);

// AccessRule: /integration/secured/parent requires ROLE_PARENT
$securityProvider->addAccessRule(
    new TestAccessRule('^/integration/secured/parent', 'ROLE_PARENT')
);

// AccessRule: /integration/secured/child requires ROLE_CHILD
$securityProvider->addAccessRule(
    new TestAccessRule('^/integration/secured/child', 'ROLE_CHILD')
);

// AccessRule: /integration/secured/* requires ROLE_USER (catch-all for secured area)
$securityProvider->addAccessRule(
    new TestAccessRule('^/integration/secured', 'ROLE_USER')
);

// Role Hierarchy: ROLE_PARENT inherits ROLE_CHILD and ROLE_USER
//                 ROLE_CHILD inherits ROLE_USER
//                 ROLE_ADMIN inherits ROLE_USER
$securityProvider->addRoleHierarchy('ROLE_CHILD', 'ROLE_USER');
$securityProvider->addRoleHierarchy('ROLE_PARENT', 'ROLE_CHILD');
$securityProvider->addRoleHierarchy('ROLE_PARENT', 'ROLE_USER');
$securityProvider->addRoleHierarchy('ROLE_ADMIN', 'ROLE_USER');

$app->service_providers = [
    $securityProvider,
    new SessionServiceProvider(),
];

return $app;
