<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Cookie;

use Oasis\Mlib\Http\Test\Helpers\ScenarioTestCase;

/**
 * Scenario-level integration tests for the Cookie module.
 *
 * Validates behavior equivalence with the Silex-era SimpleCookieProvider
 * from the user's perspective: configure → boot → request → verify response.
 *
 * Low-risk module: existing tests in SimpleCookieProviderTest already cover
 * the provider's event subscriber behavior at unit level. This class supplements
 * with scenario-level perspective (full pipeline: boot → request → response headers)
 * and references existing coverage via @see annotations where applicable.
 *
 * @see SimpleCookieProviderTest — existing unit tests for SimpleCookieProvider
 * @see ResponseCookieContainerTest — existing unit tests for ResponseCookieContainer
 */
class CookieScenarioTest extends ScenarioTestCase
{
    /**
     * R14-AC1: Cookie writing through the full request pipeline.
     *
     * Controller adds a cookie to ResponseCookieContainer → response
     * `Set-Cookie` header contains the cookie.
     *
     * This verifies the end-to-end behavior: MicroKernel registers
     * SimpleCookieProvider as EventSubscriber on KernelEvents::RESPONSE,
     * and the subscriber writes cookies from the container to the response.
     *
     * @see SimpleCookieProviderTest::testOnResponseWritesCookiesToResponseHeaders()
     *      — covers the same write behavior at unit level (mocked event)
     */
    public function testCookieWriting(): void
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
        ];

        $kernel   = $this->buildKernel($config, true);
        $response = $this->handleRequest($kernel, 'GET', '/cookie-scenario/add');

        $this->assertStatusCode($response, 200);

        // Verify Set-Cookie header is present
        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies, 'Response should contain at least one cookie');

        $cookieNames = array_map(fn($c) => $c->getName(), $cookies);
        $this->assertContains('scenario_cookie', $cookieNames);

        // Verify cookie value
        $scenarioCookie = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'scenario_cookie') {
                $scenarioCookie = $cookie;
                break;
            }
        }
        $this->assertNotNull($scenarioCookie);
        $this->assertSame('scenario_value', $scenarioCookie->getValue());
    }

    /**
     * R14-AC2: ResponseCookieContainer is available as a controller injected argument.
     *
     * Configure SimpleCookieProvider (automatically registered during boot) →
     * boot MicroKernel → verify ResponseCookieContainer is resolved by
     * ExtendedArgumentValueResolver and injected into the controller.
     *
     * In v2.5.0, SimpleCookieProvider::boot() called addControllerInjectedArg().
     * In v3.x, MicroKernel::registerCookie() does the same during boot().
     *
     * @see SimpleCookieProviderTest::testGetCookieContainerReturnsSameInstance()
     *      — covers container identity at unit level
     */
    public function testResponseCookieContainerInjection(): void
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
        ];

        $kernel   = $this->buildKernel($config, true);
        $response = $this->handleRequest($kernel, 'GET', '/cookie-scenario/verify-injection');

        // If ResponseCookieContainer was not injectable, the controller would
        // fail with an argument resolution error (500). A 200 confirms injection.
        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['injected']);
        $this->assertSame(
            'Oasis\\Mlib\\Http\\ServiceProviders\\Cookie\\ResponseCookieContainer',
            $data['class'],
        );
    }

    /**
     * R14-AC3: Multiple cookies are all written to response headers.
     *
     * Controller adds multiple cookies → all cookies appear in the response
     * Set-Cookie headers.
     *
     * @see SimpleCookieProviderTest::testOnResponseWritesMultipleCookies()
     *      — covers multiple cookie write at unit level (mocked event)
     */
    public function testMultipleCookies(): void
    {
        $config = [
            'cache_dir' => static::createTempCacheDir(),
            'routing'   => $this->createRoutingConfig(
                __DIR__ . '/scenario.routes.yml',
                ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
            ),
        ];

        $kernel   = $this->buildKernel($config, true);
        $response = $this->handleRequest($kernel, 'GET', '/cookie-scenario/multiple');

        $this->assertStatusCode($response, 200);

        $cookies     = $response->headers->getCookies();
        $cookieNames = array_map(fn($c) => $c->getName(), $cookies);

        $this->assertContains('first_cookie', $cookieNames, 'first_cookie should be in response');
        $this->assertContains('second_cookie', $cookieNames, 'second_cookie should be in response');
        $this->assertContains('third_cookie', $cookieNames, 'third_cookie should be in response');

        // Verify values
        $valueMap = [];
        foreach ($cookies as $cookie) {
            $valueMap[$cookie->getName()] = $cookie->getValue();
        }
        $this->assertSame('first_value', $valueMap['first_cookie']);
        $this->assertSame('second_value', $valueMap['second_cookie']);
        $this->assertSame('third_value', $valueMap['third_cookie']);
    }
}
