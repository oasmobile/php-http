<?php
/**
 * MicroKernel configuration for SecurityServiceProviderConfigurationTest.
 *
 * Uses config-based security setup (passed to MicroKernel constructor).
 * Equivalent to app.security.php but exercises the config-based path.
 *
 * Note: HTTP basic auth and form login policies are not implemented in Phase 3.
 * Only pre-auth (mauth) is functional. Tests for http/form auth are skipped.
 */

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;

$config = [
    'cache_dir'      => isset($cacheDir) ? $cacheDir : sys_get_temp_dir() . '/oasis-http-ut-security2',
    'routing'        => [
        'path'       => __DIR__ . '/../routes.yml',
        'namespaces' => [
            'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
        ],
    ],
    'security'       => [
        'policies'       => [
            'mauth' => new TestAuthenticationPolicy(),
        ],
        'firewalls'      => [
            'minhao.admin' => [
                'pattern'  => '^/secured/madmin',
                'policies' => [
                    'mauth' => true,
                ],
                'users'    => new TestApiUserProvider(),
            ],
        ],
        'access_rules'   => [
            [
                'pattern' => '^/secured/madmin/admin',
                'roles'   => 'ROLE_ADMIN',
            ],
            [
                'pattern' => new ChainRequestMatcher([
                    new PathRequestMatcher('^/secured/madmin/parent'),
                    new HostRequestMatcher("bai(du|da)\\.com"),
                ]),
                'roles'   => ['ROLE_PARENT'],
            ],
            [
                'pattern' => '^/secured/madmin/child',
                'roles'   => 'ROLE_CHILD',
            ],
            [
                'pattern' => '^/secured/madmin',
                'roles'   => 'ROLE_USER',
            ],
        ],
        'role_hierarchy' => [
            'ROLE_GOOD'   => 'ROLE_USER',
            'ROLE_CHILD'  => ['ROLE_USER'],
            'ROLE_PARENT' => ['ROLE_CHILD', 'ROLE_USER'],
        ],
    ],
    'view_handlers'  => [new JsonViewHandler()],
    'error_handlers' => [new JsonErrorHandler()],
];

$app = new MicroKernel($config, true);

return $app;
