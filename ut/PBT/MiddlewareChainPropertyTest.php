<?php
/**
 * Property-Based Tests for Middleware Chain.
 *
 * CP2: Middleware 优先级排序 — 任何 middleware 集合的执行顺序严格按 priority 降序。
 * CP3: Before middleware 短路 — before middleware 返回 Response 时后续 middleware 和 controller 不执行。
 * 测试 onlyForMasterRequest() 对 sub-request 的过滤。
 *
 * 集成级：启动 MicroKernel 实例。
 *
 * Ref: Requirement 15, AC 4
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Middlewares\AbstractMiddleware;
use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Views\JsonViewHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Recording middleware that logs its execution order.
 * Each instance has a configurable priority and an optional short-circuit response.
 */
class RecordingMiddleware extends AbstractMiddleware
{
    private int $beforePriority;
    private int $afterPriority;
    private bool $masterOnly;
    private ?Response $shortCircuitResponse;
    private string $id;

    /** @var array{log: list<string>} Shared log container */
    private array $logContainer;

    public function __construct(
        string $id,
        int $beforePriority,
        int $afterPriority,
        bool $masterOnly,
        ?Response $shortCircuitResponse,
        array &$logContainer
    ) {
        $this->id = $id;
        $this->beforePriority = $beforePriority;
        $this->afterPriority = $afterPriority;
        $this->masterOnly = $masterOnly;
        $this->shortCircuitResponse = $shortCircuitResponse;
        $this->logContainer = &$logContainer;
    }

    public function onlyForMasterRequest(): bool
    {
        return $this->masterOnly;
    }

    public function getBeforePriority(): int|false
    {
        return $this->beforePriority;
    }

    public function getAfterPriority(): int|false
    {
        return $this->afterPriority;
    }

    public function before(Request $request, MicroKernel $kernel): ?Response
    {
        $this->logContainer['log'][] = 'before:' . $this->id;
        return $this->shortCircuitResponse;
    }

    public function after(Request $request, Response $response): void
    {
        $this->logContainer['log'][] = 'after:' . $this->id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}

class MiddlewareChainPropertyTest extends TestCase
{
    use TestTrait;
    use RouteCacheCleaner;

    /** @var callable|null */
    private $previousExceptionHandler = null;

    protected function setUp(): void
    {
        // Save current exception handler state before creating kernels
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        $this->cleanRouteCache(__DIR__ . '/../cache');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Restore exception handler to prevent PHPUnit "did not remove its own exception handlers" warning
        while (true) {
            $current = set_exception_handler(null);
            restore_exception_handler();
            if ($current === $this->previousExceptionHandler || $current === null) {
                break;
            }
            restore_exception_handler();
        }
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }

        parent::tearDown();
    }

    // ─── CP2: Middleware 优先级排序 ──────────────────────────────────

    /**
     * For any set of middlewares with distinct priorities, the before-phase
     * execution order is strictly descending by priority (higher priority first).
     */
    public function testMiddlewareBeforeExecutionOrderFollowsPriorityDescending(): void
    {
        $this->limitTo(20)->forAll(
            // Generate 2–6 distinct priorities in the valid range
            Generators::choose(2, 6)
        )->then(function (int $count) {
            // Generate distinct priorities
            $priorities = [];
            while (count($priorities) < $count) {
                $p = random_int(-500, 500);
                if (!in_array($p, $priorities, true)) {
                    $priorities[] = $p;
                }
            }

            $logContainer = ['log' => []];
            $middlewares = [];

            foreach ($priorities as $i => $priority) {
                $middlewares[] = new RecordingMiddleware(
                    "mw-{$i}-p{$priority}",
                    $priority,
                    -$priority, // after priority is inverse for simplicity
                    false,      // not master-only, so it always runs
                    null,       // no short-circuit
                    $logContainer
                );
            }

            $kernel = $this->createKernelWithMiddlewares($middlewares);
            $request = Request::create('/', 'GET');
            $kernel->handle($request);

            // Extract before-phase entries
            $beforeEntries = array_values(array_filter(
                $logContainer['log'],
                fn(string $entry) => str_starts_with($entry, 'before:')
            ));

            // All middlewares should have executed
            $this->assertCount(
                $count,
                $beforeEntries,
                'All middlewares should execute in before phase'
            );

            // Verify descending priority order
            $executedPriorities = [];
            foreach ($beforeEntries as $entry) {
                // Extract priority from id "mw-{i}-p{priority}"
                preg_match('/p(-?\d+)$/', $entry, $matches);
                $this->assertNotEmpty($matches, "Could not extract priority from: {$entry}");
                $executedPriorities[] = (int)$matches[1];
            }

            // Should be in descending order
            $sorted = $executedPriorities;
            usort($sorted, fn(int $a, int $b) => $b <=> $a);
            $this->assertEquals(
                $sorted,
                $executedPriorities,
                sprintf(
                    'Before-phase execution order should be descending by priority. Expected: [%s], Got: [%s]',
                    implode(', ', $sorted),
                    implode(', ', $executedPriorities)
                )
            );

            $kernel->shutdown();
        });
    }

    // ─── CP3: Before middleware 短路 ─────────────────────────────────

    /**
     * When a before-middleware returns a Response, subsequent middlewares'
     * before() and the controller are NOT executed.
     */
    public function testBeforeMiddlewareShortCircuitPreventsSubsequentExecution(): void
    {
        $this->limitTo(20)->forAll(
            // Total middleware count (3–6)
            Generators::choose(3, 6),
            // Index of the short-circuiting middleware (0-based, will be clamped)
            Generators::choose(0, 5)
        )->then(function (int $totalCount, int $shortCircuitIndex) {
            $shortCircuitIndex = min($shortCircuitIndex, $totalCount - 1);

            $logContainer = ['log' => []];
            $middlewares = [];

            // Assign priorities in descending order so execution order is deterministic:
            // middleware 0 has highest priority, middleware N-1 has lowest
            for ($i = 0; $i < $totalCount; $i++) {
                $priority = 500 - ($i * 100);
                $shortCircuitResponse = ($i === $shortCircuitIndex)
                    ? new Response('short-circuited', 200)
                    : null;

                $middlewares[] = new RecordingMiddleware(
                    "mw-{$i}",
                    $priority,
                    MicroKernel::AFTER_PRIORITY_LATEST,
                    false,
                    $shortCircuitResponse,
                    $logContainer
                );
            }

            $kernel = $this->createKernelWithMiddlewares($middlewares);
            $request = Request::create('/', 'GET');
            $response = $kernel->handle($request);

            // The response should be the short-circuit response
            $this->assertEquals(
                'short-circuited',
                $response->getContent(),
                'Response should be from the short-circuiting middleware'
            );

            // Extract before-phase entries
            $beforeEntries = array_values(array_filter(
                $logContainer['log'],
                fn(string $entry) => str_starts_with($entry, 'before:')
            ));

            // Only middlewares up to and including the short-circuiting one should have executed
            $expectedBeforeCount = $shortCircuitIndex + 1;
            $this->assertCount(
                $expectedBeforeCount,
                $beforeEntries,
                sprintf(
                    'Only %d middlewares should execute before short-circuit at index %d, but %d executed: [%s]',
                    $expectedBeforeCount,
                    $shortCircuitIndex,
                    count($beforeEntries),
                    implode(', ', $beforeEntries)
                )
            );

            // Verify the last before entry is the short-circuiting middleware
            $lastBefore = end($beforeEntries);
            $this->assertStringContainsString(
                "mw-{$shortCircuitIndex}",
                $lastBefore,
                'Last before entry should be the short-circuiting middleware'
            );

            $kernel->shutdown();
        });
    }

    // ─── onlyForMasterRequest() 过滤 ────────────────────────────────

    /**
     * Middlewares with onlyForMasterRequest() = true do NOT execute for sub-requests.
     */
    public function testOnlyForMasterRequestFilteringOnSubRequests(): void
    {
        $this->limitTo(20)->forAll(
            Generators::choose(2, 5)
        )->then(function (int $count) {
            $logContainer = ['log' => []];
            $middlewares = [];

            // Create middlewares: even-indexed are master-only, odd-indexed are not
            for ($i = 0; $i < $count; $i++) {
                $masterOnly = ($i % 2 === 0);
                $middlewares[] = new RecordingMiddleware(
                    "mw-{$i}" . ($masterOnly ? '-master' : '-all'),
                    500 - ($i * 50),
                    MicroKernel::AFTER_PRIORITY_LATEST,
                    $masterOnly,
                    null,
                    $logContainer
                );
            }

            $kernel = $this->createKernelWithMiddlewares($middlewares);

            // Send a sub-request
            $request = Request::create('/', 'GET');
            $kernel->handle($request, HttpKernelInterface::SUB_REQUEST);

            // Extract before-phase entries
            $beforeEntries = array_values(array_filter(
                $logContainer['log'],
                fn(string $entry) => str_starts_with($entry, 'before:')
            ));

            // Only non-master-only middlewares should have executed
            foreach ($beforeEntries as $entry) {
                $this->assertStringNotContainsString(
                    '-master',
                    $entry,
                    sprintf('Master-only middleware should not execute for sub-request: %s', $entry)
                );
            }

            // Count expected non-master-only middlewares
            $expectedCount = count(array_filter(
                range(0, $count - 1),
                fn(int $i) => $i % 2 !== 0
            ));
            $this->assertCount(
                $expectedCount,
                $beforeEntries,
                'Only non-master-only middlewares should execute for sub-requests'
            );

            $kernel->shutdown();
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Create a MicroKernel with the given middlewares and a simple route.
     *
     * @param RecordingMiddleware[] $middlewares
     */
    private function createKernelWithMiddlewares(array $middlewares): MicroKernel
    {
        $cacheDir = static::createTempCacheDir() . '/mw-' . bin2hex(random_bytes(4));
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $config = [
            'cache_dir'     => $cacheDir,
            'routing'       => [
                'path'       => __DIR__ . '/../routes.yml',
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\',
                ],
            ],
            'middlewares'    => $middlewares,
            'view_handlers' => [new JsonViewHandler()],
        ];

        return new MicroKernel($config, true);
    }
}
