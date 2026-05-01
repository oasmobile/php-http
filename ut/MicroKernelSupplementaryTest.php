<?php
declare(strict_types=1);

/**
 * Supplementary unit tests for MicroKernel.
 *
 * Covers uncovered branches: trusted_proxies single value, providers config,
 * providers invalid config, getRouter() with/without routing, run() method,
 * getErrorHandlers(), getViewHandlers().
 */

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\HttpFoundation\Request;

class MicroKernelSupplementaryTest extends TestCase
{
    use RouteCacheCleaner;

    /** @var int|null */
    private $savedTrustedHeaderSet;
    /** @var array */
    private $savedTrustedProxies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedTrustedProxies   = Request::getTrustedProxies();
        $this->savedTrustedHeaderSet = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->savedTrustedProxies, $this->savedTrustedHeaderSet);
        restore_exception_handler();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // trusted_proxies — single string value (non-array)
    // ---------------------------------------------------------------

    public function testConfigTrustedProxiesSingleStringValue(): void
    {
        Request::setTrustedProxies([], Request::getTrustedHeaderSet());

        new MicroKernel(['trusted_proxies' => '192.168.1.1'], true);

        $proxies = Request::getTrustedProxies();
        $this->assertContains('192.168.1.1', $proxies);
    }

    // ---------------------------------------------------------------
    // providers — valid CompilerPassInterface
    // ---------------------------------------------------------------

    public function testConfigProvidersWithCompilerPass(): void
    {
        $pass = $this->createStub(CompilerPassInterface::class);
        // Just verify the config is accepted and boot succeeds
        $app = new MicroKernel(['providers' => [$pass]], true);
        $app->boot();

        $this->assertInstanceOf(MicroKernel::class, $app);
    }

    /**
     * providers config with a single (non-array) CompilerPass should be normalized.
     */
    public function testConfigProvidersSingleValue(): void
    {
        $pass = $this->createStub(CompilerPassInterface::class);
        $app = new MicroKernel(['providers' => $pass], true);
        $app->boot();

        $this->assertInstanceOf(MicroKernel::class, $app);
    }

    /**
     * providers config with invalid value should throw.
     */
    public function testConfigProvidersInvalidValueThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('providers must be an array of CompilerPassInterface or ExtensionInterface');

        new MicroKernel(['providers' => ['not_a_provider']], true);
    }

    // ---------------------------------------------------------------
    // getRouter() — with and without routing config
    // ---------------------------------------------------------------

    public function testGetRouterReturnsNullWithoutRoutingConfig(): void
    {
        $app = new MicroKernel([], true);
        $app->boot();

        $this->assertNull($app->getRouter());
    }

    public function testGetRouterReturnsRouterWithRoutingConfig(): void
    {
        $app = new MicroKernel(
            [
                'cache_dir' => static::createTempCacheDir(),
                'routing'   => [
                    'path'       => __DIR__ . '/routes.yml',
                    'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
                ],
            ],
            true
        );
        $app->boot();

        $router = $app->getRouter();
        $this->assertInstanceOf(\Symfony\Component\Routing\Router::class, $router);
    }

    // ---------------------------------------------------------------
    // getErrorHandlers() / getViewHandlers()
    // ---------------------------------------------------------------

    public function testGetErrorHandlersReturnsConfiguredHandlers(): void
    {
        $handler = function () { return null; };
        $app = new MicroKernel(['error_handlers' => [$handler]], true);

        $handlers = $app->getErrorHandlers();
        $this->assertCount(1, $handlers);
        $this->assertSame($handler, $handlers[0]);
    }

    public function testGetViewHandlersReturnsConfiguredHandlers(): void
    {
        $handler = new JsonViewHandler();
        $app = new MicroKernel(['view_handlers' => [$handler]], true);

        $handlers = $app->getViewHandlers();
        $this->assertCount(1, $handlers);
        $this->assertSame($handler, $handlers[0]);
    }

    // ---------------------------------------------------------------
    // run() — basic execution
    // ---------------------------------------------------------------

    public function testRunExecutesRequestAndSendsResponse(): void
    {
        $cacheDir = static::createTempCacheDir();
        $app = new MicroKernel(
            [
                'cache_dir' => $cacheDir,
                'routing'   => [
                    'path'       => __DIR__ . '/routes.yml',
                    'namespaces' => ['Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\'],
                ],
                'view_handlers' => [new JsonViewHandler()],
            ],
            true
        );

        $request = Request::create('/');

        // Capture output from run() since it calls $response->send()
        ob_start();
        $app->run($request);
        $output = ob_get_clean();

        // The home route should produce some output
        $this->assertNotEmpty($output);
    }
}
