<?php
declare(strict_types=1);

/**
 * CORS module scenario tests.
 *
 * Verifies CORS behavior from a user-scenario perspective:
 * construct MicroKernel → configure CORS strategies → boot → send request → assert response headers.
 *
 * These tests establish a behavioral baseline for the Silex → Symfony migration
 * audit, complementing existing unit/integration tests with scenario-level coverage.
 *
 * @see CrossOriginResourceSharingTest for existing preflight/normal request tests
 * @see CrossOriginResourceSharingAdvancedTest for existing CORS+Security tests
 */

namespace Oasis\Mlib\Http\Test\Cors;

use Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler;
use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleFirewall;
use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;
use Oasis\Mlib\Http\Test\Helpers\Security\TestApiUserProvider;
use Oasis\Mlib\Http\Test\Helpers\Security\TestAuthenticationPolicy;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use Symfony\Component\HttpFoundation\Response;

class CorsScenarioTest extends ScenarioTestCase
{
    /**
     * Build a base config with routing pointing to CORS scenario routes.
     *
     * @param array<array<string, mixed>> $corsStrategies CORS strategy configurations
     * @param array<string, mixed>        $extra          Additional config to merge
     *
     * @return array<string, mixed>
     */
    private function buildCorsConfig(array $corsStrategies, array $extra = []): array
    {
        return array_merge(
            [
                'cache_dir'      => static::createTempCacheDir(),
                'routing'        => $this->createRoutingConfig(
                    __DIR__ . '/scenario.routes.yml',
                    ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
                ),
                'view_handlers'  => [new JsonViewHandler()],
                'error_handlers' => [new JsonErrorHandler()],
                'cors'           => $corsStrategies,
            ],
            $extra,
        );
    }

    // -----------------------------------------------------------------
    // R8-AC1: Preflight request handling
    // -----------------------------------------------------------------

    /**
     * Configure CORS strategy → boot MicroKernel → send OPTIONS request with
     * Access-Control-Request-Method header → verify preflight response with
     * correct Access-Control-Allow-* headers.
     */
    public function testPreflightRequestHandling(): void
    {
        $config = $this->buildCorsConfig([
            [
                'pattern' => '/scenario/cors/.*',
                'origins' => ['example.com', 'test.org'],
                'headers' => ['X-Custom-Header', 'Authorization'],
                'max_age' => 3600,
            ],
        ]);

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest(
            $kernel,
            'OPTIONS',
            '/scenario/cors/resource',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN  => 'http://example.com',
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD  => 'PUT',
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_HEADERS => 'X-Custom-Header',
            ],
        );

        // Preflight returns 204 No Content
        $this->assertStatusCode($response, Response::HTTP_NO_CONTENT);

        // Access-Control-Allow-Origin should be set to the request origin
        $this->assertTrue(
            $response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
            'Preflight response must include Access-Control-Allow-Origin',
        );
        $this->assertSame(
            'http://example.com',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
        );

        // Access-Control-Max-Age should reflect configured max_age
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_MAX_AGE));
        $this->assertEquals(3600, $response->headers->get(CrossOriginResourceSharingProvider::HEADER_MAX_AGE));

        // Access-Control-Allow-Methods should include the requested method (non-simple)
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertStringContainsString(
            'PUT',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS),
        );

        // Access-Control-Allow-Headers should include configured headers
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
        $this->assertStringContainsString(
            'X-Custom-Header',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS),
        );

        // Non-wildcard origin → Vary: Origin
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_VARY));
    }

    // -----------------------------------------------------------------
    // R8-AC2: Normal CORS request
    // -----------------------------------------------------------------

    /**
     * Send a cross-origin GET request → verify response includes
     * Access-Control-Allow-Origin header matching the strategy.
     */
    public function testNormalCorsRequest(): void
    {
        $config = $this->buildCorsConfig([
            [
                'pattern'         => '/scenario/cors/.*',
                'origins'         => ['example.com'],
                'headers_exposed' => ['X-Request-Id', 'X-Trace-Id'],
            ],
        ]);

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest(
            $kernel,
            'GET',
            '/scenario/cors/home',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'http://example.com',
            ],
        );

        $this->assertStatusCode($response, Response::HTTP_OK);

        // Access-Control-Allow-Origin should be set
        $this->assertTrue(
            $response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
            'Normal CORS response must include Access-Control-Allow-Origin',
        );
        $this->assertSame(
            'http://example.com',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
        );

        // Exposed headers should be listed
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));
        $exposed = $response->headers->get(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS);
        $this->assertStringContainsString('X-Request-Id', $exposed);
        $this->assertStringContainsString('X-Trace-Id', $exposed);

        // Normal request should NOT have Allow-Methods or Allow-Headers
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_METHODS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));

        // Non-wildcard origin → Vary: Origin
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_VARY));
    }

    // -----------------------------------------------------------------
    // R8-AC3: Multiple CORS strategy matching
    // -----------------------------------------------------------------

    /**
     * Configure two strategies with different URL patterns → verify each
     * strategy applies only to its matched pattern.
     *
     * Strategy 1: /scenario/cors/.* — specific origins, no credentials
     * Strategy 2: /scenario/api/.* — wildcard origin, with credentials
     */
    public function testMultipleCorsStrategyMatching(): void
    {
        $config = $this->buildCorsConfig([
            [
                'pattern'             => '/scenario/cors/.*',
                'origins'             => ['example.com'],
                'credentials_allowed' => false,
            ],
            [
                'pattern'             => '/scenario/api/.*',
                'origins'             => '*',
                'credentials_allowed' => true,
                'headers_exposed'     => ['X-Api-Version'],
            ],
        ]);

        $kernel = $this->buildKernel($config);

        // Request to /scenario/cors/home → strategy 1 (specific origin, no credentials)
        $response1 = $this->handleRequest(
            $kernel,
            'GET',
            '/scenario/cors/home',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'http://example.com',
            ],
        );

        $this->assertStatusCode($response1, Response::HTTP_OK);
        $this->assertTrue($response1->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        $this->assertSame(
            'http://example.com',
            $response1->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
        );
        // Strategy 1 has no credentials
        $this->assertFalse($response1->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        // Strategy 1 has no exposed headers
        $this->assertFalse($response1->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));

        // Request to /scenario/api/data → strategy 2 (wildcard origin, credentials)
        $kernel2   = $this->buildKernel($config);
        $response2 = $this->handleRequest(
            $kernel2,
            'GET',
            '/scenario/api/data',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'http://any-origin.io',
            ],
        );

        $this->assertStatusCode($response2, Response::HTTP_OK);
        $this->assertTrue($response2->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN));
        // Credentials allowed → origin echoed (not wildcard *)
        $this->assertSame(
            'http://any-origin.io',
            $response2->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
        );
        $this->assertTrue($response2->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        $this->assertSame(
            'true',
            $response2->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS),
        );
        // Strategy 2 has exposed headers
        $this->assertTrue($response2->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));
        $this->assertStringContainsString(
            'X-Api-Version',
            $response2->headers->get(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS),
        );
    }

    // -----------------------------------------------------------------
    // R8-AC4: CORS with credentials
    // -----------------------------------------------------------------

    /**
     * Configure a strategy with credentials = true → verify
     * Access-Control-Allow-Credentials: true header is present in the response.
     */
    public function testCorsWithCredentials(): void
    {
        $config = $this->buildCorsConfig([
            [
                'pattern'             => '/scenario/cors/.*',
                'origins'             => '*',
                'credentials_allowed' => true,
            ],
        ]);

        $kernel = $this->buildKernel($config);

        // Preflight with credentials
        $preflightResponse = $this->handleRequest(
            $kernel,
            'OPTIONS',
            '/scenario/cors/resource',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'http://credentialed.app',
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'GET',
            ],
        );

        $this->assertStatusCode($preflightResponse, Response::HTTP_NO_CONTENT);
        $this->assertTrue(
            $preflightResponse->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS),
        );
        $this->assertSame(
            'true',
            $preflightResponse->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS),
        );
        // When credentials allowed, origin is echoed (not wildcard *)
        $this->assertSame(
            'http://credentialed.app',
            $preflightResponse->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
        );

        // Normal request with credentials
        $kernel2         = $this->buildKernel($config);
        $normalResponse = $this->handleRequest(
            $kernel2,
            'GET',
            '/scenario/cors/home',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'http://credentialed.app',
            ],
        );

        $this->assertStatusCode($normalResponse, Response::HTTP_OK);
        $this->assertTrue(
            $normalResponse->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS),
        );
        $this->assertSame(
            'true',
            $normalResponse->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS),
        );
        $this->assertSame(
            'http://credentialed.app',
            $normalResponse->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
        );
    }

    // -----------------------------------------------------------------
    // R8-AC5: CORS and Security interaction
    // -----------------------------------------------------------------

    /**
     * Configure both CORS and Security → send a preflight request to a secured
     * endpoint → verify the preflight response is returned without triggering
     * authentication (preflight bypasses firewall).
     *
     * The CORS onPostRouting (priority 20) sets the preflight response BEFORE
     * the firewall (priority 8) runs, so the firewall never processes the request.
     */
    public function testCorsAndSecurityInteraction(): void
    {
        $config = $this->buildCorsConfig(
            [
                [
                    'pattern' => '/scenario/secured/.*',
                    'origins' => ['http://secure-app.com', 'secure-app.com'],
                    'headers' => ['Authorization'],
                ],
            ],
            [
                'security' => [
                    'policies'     => [
                        'mauth' => new TestAuthenticationPolicy(),
                    ],
                    'firewalls'    => [
                        'secured' => new SimpleFirewall([
                            'pattern'  => '^/scenario/secured',
                            'policies' => ['mauth' => true],
                            'users'    => new TestApiUserProvider(),
                        ]),
                    ],
                    'access_rules' => [
                        ['pattern' => '^/scenario/secured', 'roles' => 'ROLE_USER'],
                    ],
                ],
            ],
        );

        $kernel   = $this->buildKernel($config);
        $response = $this->handleRequest(
            $kernel,
            'OPTIONS',
            '/scenario/secured/cors',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN  => 'http://secure-app.com',
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD  => 'GET',
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_HEADERS => 'Authorization',
            ],
        );

        // Preflight should return 204 (not 403 from security)
        $this->assertStatusCode($response, Response::HTTP_NO_CONTENT);

        // CORS headers should be present
        $this->assertTrue(
            $response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
            'Preflight to secured endpoint must include CORS headers without triggering auth',
        );
        $this->assertSame(
            'http://secure-app.com',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
        );

        // Allow-Headers should include Authorization
        $this->assertTrue($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS));
        $this->assertStringContainsString(
            'Authorization',
            $response->headers->get(CrossOriginResourceSharingProvider::HEADER_ALLOW_HEADERS),
        );
    }

    // -----------------------------------------------------------------
    // R8-AC6: Non-matching origin
    // -----------------------------------------------------------------

    /**
     * Send a cross-origin request with an origin not in the allowed list →
     * verify no Access-Control-Allow-Origin header is added.
     */
    public function testNonMatchingOrigin(): void
    {
        $config = $this->buildCorsConfig([
            [
                'pattern' => '/scenario/cors/.*',
                'origins' => ['allowed.com', 'trusted.org'],
            ],
        ]);

        $kernel = $this->buildKernel($config);

        // Normal request with disallowed origin
        $response = $this->handleRequest(
            $kernel,
            'GET',
            '/scenario/cors/home',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'http://evil.com',
            ],
        );

        // Request should still succeed (CORS doesn't block server-side)
        $this->assertStatusCode($response, Response::HTTP_OK);

        // But no CORS headers should be present
        $this->assertFalse(
            $response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
            'Non-matching origin must not receive Access-Control-Allow-Origin header',
        );
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_CREDENTIALS));
        $this->assertFalse($response->headers->has(CrossOriginResourceSharingProvider::HEADER_EXPOSE_HEADERS));

        // Preflight with disallowed origin
        $kernel2           = $this->buildKernel($config);
        $preflightResponse = $this->handleRequest(
            $kernel2,
            'OPTIONS',
            '/scenario/cors/resource',
            [],
            [
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_ORIGIN => 'http://evil.com',
                'HTTP_' . CrossOriginResourceSharingProvider::HEADER_REQUEST_METHOD => 'PUT',
            ],
        );

        // Preflight still returns 204 (the response is created) but no CORS headers
        $this->assertStatusCode($preflightResponse, Response::HTTP_NO_CONTENT);
        $this->assertFalse(
            $preflightResponse->headers->has(CrossOriginResourceSharingProvider::HEADER_ALLOW_ORIGIN),
            'Preflight with non-matching origin must not receive Access-Control-Allow-Origin header',
        );
    }
}
