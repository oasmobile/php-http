<?php
/**
 * MicroKernel configuration for SecurityServiceProviderTest.
 *
 * Provides a complete Policy → Firewall → AccessRule → Role Hierarchy chain
 * using programmatic SimpleSecurityProvider API for testing.
 *
 * Note: HTTP basic auth and form login policies are not implemented in Phase 3.
 * Only pre-auth (mauth) is functional. Tests for http/form auth are skipped.
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;

$testFirewall = new SimpleFirewall(
    [
        'pattern'  => '^/secured/madmin',
        'policies' => [
            'mauth' => true,
        ],
        'users'    => new TestApiUserProvider(),
    ]
);

$config = [
    'cache_dir'      => isset($cacheDir) ? $cacheDir : sys_get_temp_dir() . '/oasis-http-ut-security',
    'routing'        => [
        'path'       => __DIR__ . '/../routes.yml',
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
        ],
    ],
    'view_handlers'  => [new JsonViewHandler()],
    'error_handlers' => [new JsonErrorHandler()],
    'security'       => [
        'policies'       => [
            'mauth' => new TestAuthenticationPolicy(),
        ],
        'firewalls'      => [
            'minhao.admin' => $testFirewall,
        ],
        'access_rules'   => [
            ['pattern' => '^/secured/madmin/admin', 'roles' => 'ROLE_ADMIN'],
            [
                'pattern' => new ChainRequestMatcher([
                    new PathRequestMatcher('^/secured/madmin/parent'),
                    new HostRequestMatcher("bai(du|da)\\.com"),
                ]),
                'roles'   => ['ROLE_PARENT'],
            ],
            ['pattern' => '^/secured/madmin/child', 'roles' => 'ROLE_CHILD'],
            ['pattern' => '^/secured/madmin', 'roles' => 'ROLE_USER'],
        ],
        'role_hierarchy' => [
            'ROLE_GOOD'   => 'ROLE_USER',
            'ROLE_CHILD'  => ['ROLE_USER'],
            'ROLE_PARENT' => ['ROLE_CHILD', 'ROLE_USER'],
        ],
    ],
];

$app = new MicroKernel($config, true);

return $app;
