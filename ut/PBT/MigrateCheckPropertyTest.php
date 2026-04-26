<?php
/**
 * Property-Based Tests for Check Script (bin/oasis-http-migrate-v3-check).
 *
 * Feature: php85-migration-guide
 *
 * Property 5:  规则检测完整性
 * Property 6:  递归扫描完整性
 * Property 7:  Finding 字段完整性
 * Property 8:  Severity 分组排序
 * Property 9:  退出码正确性
 * Property 10: 输出格式有效性
 * Property 11: 二进制文件与非 UTF-8 文件容错
 *
 * 使用 Eris 生成随机 PHP 文件内容和目录结构，验证 scanner 行为。
 *
 * 性能优化：P5/P6/P7/P10/P11 直接调用 check script 内部函数（通过
 * require_once + 条件守卫），避免每次迭代 fork 进程。P8/P9 需要验证
 * CLI 输出格式和退出码，保留 proc_open 但降低迭代次数。
 *
 * Ref: Requirement 12, AC 1–8; Requirement 13, AC 2/3/6;
 *      Requirement 14, AC 5/6; Requirement 15, AC 3/4;
 *      Design Correctness Properties 5–11, Testing Strategy PBT
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

// Load check script functions without triggering main().
require_once __DIR__ . '/../../bin/oasis-http-migrate-v3-check';

class MigrateCheckPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Rule definitions for generators ────────────────────────────

    private const REMOVED_CLASSES = [
        'SilexKernel',
        'Silex\\Application',
        'Pimple\\Container',
        'Pimple\\ServiceProviderInterface',
        'Silex\\Api\\BootableProviderInterface',
        'Twig_Environment',
        'Twig_SimpleFunction',
        'Twig_Error_Loader',
    ];

    private const CHANGED_APIS = [
        'AuthenticationPolicyInterface',
        'FirewallInterface',
        'AccessRuleInterface',
        'AbstractSimplePreAuthenticator',
        'AbstractSimplePreAuthenticateUserProvider',
        'MiddlewareInterface',
        'ResponseRendererInterface',
    ];

    private const OLD_EVENTS = [
        'FilterResponseEvent',
        'GetResponseEvent',
        'GetResponseForExceptionEvent',
        'MASTER_REQUEST',
    ];

    private const REMOVED_PACKAGES = [
        'silex/silex',
        'silex/providers',
        'twig/extensions',
    ];

    private const PIMPLE_SNIPPETS = [
        '$app[\'db\']',
        '$app[\'session\']',
        '$app[\'twig\']',
        '$container[\'logger\']',
        '$container[\'mailer\']',
    ];

    private const GUZZLE_SNIPPETS = [
        "'exceptions' => false",
        "'exceptions' => true",
    ];

    // ─── Helpers ─────────────────────────────────────────────────────

    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->tempDirs = [];
        parent::tearDown();
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/migrate-check-pbt-' . bin2hex(random_bytes(8));
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $path = $item->getPathname();
            if (is_link($path)) {
                unlink($path);
            } elseif ($item->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function generatePhpFileWithUse(string $className): string
    {
        $shortName = basename(str_replace('\\', '/', $className));

        return "<?php\n\nuse {$className};\n\nclass MyClass {\n    public function test(): void {\n        \$obj = new {$shortName}();\n    }\n}\n";
    }

    private function generatePhpFileWithPimple(string $snippet): string
    {
        return "<?php\n\nclass MyService {\n    public function boot(\$app): void {\n        \$service = {$snippet};\n    }\n}\n";
    }

    private function generatePhpFileWithGuzzle(string $snippet): string
    {
        return "<?php\n\nuse GuzzleHttp\\Client;\n\n\$client = new Client([\n    {$snippet},\n]);\n";
    }

    private function generateComposerJsonWithPackage(string $package): string
    {
        return json_encode([
            'require' => [
                $package => '^1.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }



    // ─── Property 5: 规则检测完整性 ─────────────────────────────────

    /**
     * Feature: php85-migration-guide, Property 5: 规则检测完整性
     *
     * For any PHP file containing a reference to a pattern registered in the
     * Rule Registry, the scanner SHALL produce at least one Finding for that file.
     *
     * 直接调用 scanPhpFile() / scanComposerJson()，避免 fork 进程。
     *
     * Ref: Requirement 12, AC 3–8; Design Correctness Property 5
     */
    public function testRuleDetectionCompleteness(): void
    {
        $rules = \getRules();

        $allPatterns = array_merge(
            array_map(fn(string $cls) => ['type' => 'use', 'value' => $cls], self::REMOVED_CLASSES),
            array_map(fn(string $cls) => ['type' => 'use', 'value' => $cls], self::CHANGED_APIS),
            array_map(fn(string $cls) => ['type' => 'use', 'value' => $cls], self::OLD_EVENTS),
            array_map(fn(string $s) => ['type' => 'pimple', 'value' => $s], self::PIMPLE_SNIPPETS),
            array_map(fn(string $s) => ['type' => 'guzzle', 'value' => $s], self::GUZZLE_SNIPPETS),
            array_map(fn(string $p) => ['type' => 'composer', 'value' => $p], self::REMOVED_PACKAGES),
        );

        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::elements($allPatterns)
        )->withMaxSize(count($allPatterns))->then(function (array $pattern) use ($rules) {
            $dir = $this->createTempDir();

            switch ($pattern['type']) {
                case 'use':
                    $content = $this->generatePhpFileWithUse($pattern['value']);
                    $filePath = $dir . '/test.php';
                    file_put_contents($filePath, $content);
                    $findings = \scanPhpFile($filePath, $content, $rules, $dir);
                    break;
                case 'pimple':
                    $content = $this->generatePhpFileWithPimple($pattern['value']);
                    $filePath = $dir . '/test.php';
                    file_put_contents($filePath, $content);
                    $findings = \scanPhpFile($filePath, $content, $rules, $dir);
                    break;
                case 'guzzle':
                    $content = $this->generatePhpFileWithGuzzle($pattern['value']);
                    $filePath = $dir . '/test.php';
                    file_put_contents($filePath, $content);
                    $findings = \scanPhpFile($filePath, $content, $rules, $dir);
                    break;
                case 'composer':
                    $content = $this->generateComposerJsonWithPackage($pattern['value']);
                    $filePath = $dir . '/composer.json';
                    file_put_contents($filePath, $content);
                    $findings = \scanComposerJson($filePath, $rules, $dir);
                    break;
                default:
                    $findings = [];
            }

            $this->assertNotEmpty(
                $findings,
                sprintf(
                    "Scanner should produce at least one Finding for pattern '%s' (type: %s)",
                    $pattern['value'],
                    $pattern['type']
                )
            );
        });
    }

    // ─── Property 6: 递归扫描完整性 ─────────────────────────────────

    /**
     * Feature: php85-migration-guide, Property 6: 递归扫描完整性
     *
     * For any directory structure containing .php files at arbitrary nesting
     * depths (1–5 levels), the scanner SHALL discover and scan every .php file.
     *
     * 直接调用 scanDirectory()，避免 fork 进程。
     *
     * Ref: Requirement 12, AC 2; Design Correctness Property 6
     */
    public function testRecursiveScanCompleteness(): void
    {
        $rules = \getRules();

        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::choose(1, 5),
            Generators::choose(1, 3)
        )->then(function (int $depth, int $filesPerLevel) use ($rules) {
            $dir = $this->createTempDir();
            $placedFiles = 0;

            $currentPath = $dir;
            for ($level = 0; $level < $depth; $level++) {
                $subDir = $currentPath . '/level' . $level;
                mkdir($subDir, 0777, true);
                $currentPath = $subDir;

                for ($f = 0; $f < $filesPerLevel; $f++) {
                    $className = self::REMOVED_CLASSES[$placedFiles % count(self::REMOVED_CLASSES)];
                    $content = $this->generatePhpFileWithUse($className);
                    file_put_contents($currentPath . "/file{$f}.php", $content);
                    $placedFiles++;
                }
            }

            $findings = \scanDirectory($dir, $rules);

            $foundFiles = array_unique(array_column($findings, 'file'));

            $this->assertCount(
                $placedFiles,
                $foundFiles,
                sprintf(
                    'Scanner should find all %d placed PHP files, but found %d. Files found: [%s]',
                    $placedFiles,
                    count($foundFiles),
                    implode(', ', $foundFiles)
                )
            );
        });
    }

    // ─── Property 7: Finding 字段完整性 ─────────────────────────────

    /**
     * Feature: php85-migration-guide, Property 7: Finding 字段完整性
     *
     * For any Finding produced by the scanner, the Finding SHALL contain all
     * four required fields: file (relative path), line (positive integer),
     * issue (non-empty string), and action (non-empty string).
     *
     * 直接调用 scanPhpFile()，避免 fork 进程。
     *
     * Ref: Requirement 13, AC 2; Design Correctness Property 7
     */
    public function testFindingFieldCompleteness(): void
    {
        $rules = \getRules();

        $simplePatterns = array_merge(
            self::REMOVED_CLASSES,
            self::CHANGED_APIS,
            self::OLD_EVENTS,
        );

        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::elements($simplePatterns)
        )->then(function (string $className) use ($rules) {
            $dir = $this->createTempDir();
            $content = $this->generatePhpFileWithUse($className);
            $filePath = $dir . '/test.php';
            file_put_contents($filePath, $content);

            $findings = \scanPhpFile($filePath, $content, $rules, $dir);

            $this->assertNotEmpty($findings, "Should produce at least one finding for {$className}");

            foreach ($findings as $i => $finding) {
                $this->assertArrayHasKey('file', $finding, "Finding #{$i} must have 'file' field");
                $this->assertIsString($finding['file'], "Finding #{$i} 'file' must be a string");
                $this->assertNotEmpty($finding['file'], "Finding #{$i} 'file' must be non-empty");

                $this->assertArrayHasKey('line', $finding, "Finding #{$i} must have 'line' field");
                $this->assertIsInt($finding['line'], "Finding #{$i} 'line' must be an integer");
                $this->assertGreaterThan(0, $finding['line'], "Finding #{$i} 'line' must be positive");

                $this->assertArrayHasKey('issue', $finding, "Finding #{$i} must have 'issue' field");
                $this->assertIsString($finding['issue'], "Finding #{$i} 'issue' must be a string");
                $this->assertNotEmpty($finding['issue'], "Finding #{$i} 'issue' must be non-empty");

                $this->assertArrayHasKey('action', $finding, "Finding #{$i} must have 'action' field");
                $this->assertIsString($finding['action'], "Finding #{$i} 'action' must be a string");
                $this->assertNotEmpty($finding['action'], "Finding #{$i} 'action' must be non-empty");
            }
        });
    }

    // ─── Property 8: Severity 分组排序 ──────────────────────────────

    /**
     * Feature: php85-migration-guide, Property 8: Severity 分组排序
     *
     * For any set of Findings with mixed Severity_Levels, the text output
     * SHALL group all 🔴 findings before all 🟡 findings, and all 🟡
     * findings before all 🟢 findings.
     *
     * 直接调用 scanDirectory() + reportText()，通过 output buffering 捕获
     * text 输出，避免 fork 进程。
     *
     * Ref: Requirement 13, AC 3; Design Correctness Property 8
     */
    public function testSeverityGroupOrdering(): void
    {
        $rules = \getRules();

        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::elements(self::REMOVED_CLASSES),
            Generators::elements(self::GUZZLE_SNIPPETS)
        )->withMaxSize(20)->then(function (string $removedClass, string $guzzleSnippet) use ($rules) {
            $dir = $this->createTempDir();

            file_put_contents(
                $dir . '/red_finding.php',
                $this->generatePhpFileWithUse($removedClass)
            );

            file_put_contents(
                $dir . '/yellow_finding.php',
                $this->generatePhpFileWithGuzzle($guzzleSnippet)
            );

            $findings = \scanDirectory($dir, $rules);
            $this->assertNotEmpty($findings, 'Should produce findings for mixed severity test');

            ob_start();
            \reportText($findings, $dir);
            $stdout = ob_get_clean();

            $redPos = mb_strpos($stdout, '🔴');
            $yellowPos = mb_strpos($stdout, '🟡');

            $this->assertNotFalse($redPos, "Text output should contain 🔴 section. stdout: {$stdout}");
            $this->assertNotFalse($yellowPos, "Text output should contain 🟡 section. stdout: {$stdout}");
            $this->assertLessThan(
                $yellowPos,
                $redPos,
                "🔴 section should appear before 🟡 section in text output"
            );
        });
    }

    // ─── Property 9: 退出码正确性 ───────────────────────────────────

    /**
     * Feature: php85-migration-guide, Property 9: 退出码正确性
     *
     * For any scan result, the exit code SHALL be 1 if and only if at least
     * one Finding has Severity_Level 🔴; otherwise the exit code SHALL be 0.
     *
     * 直接调用 main()，通过 output buffering 抑制输出，检查返回的退出码。
     *
     * Ref: Requirement 13, AC 6; Design Correctness Property 9
     */
    public function testExitCodeCorrectness(): void
    {
        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::elements([true, false])
        )->withMaxSize(10)->then(function (bool $hasRedFinding) {
            $dir = $this->createTempDir();

            if ($hasRedFinding) {
                $className = self::REMOVED_CLASSES[array_rand(self::REMOVED_CLASSES)];
                file_put_contents(
                    $dir . '/test.php',
                    $this->generatePhpFileWithUse($className)
                );
            } else {
                $snippet = self::GUZZLE_SNIPPETS[array_rand(self::GUZZLE_SNIPPETS)];
                file_put_contents(
                    $dir . '/test.php',
                    $this->generatePhpFileWithGuzzle($snippet)
                );
            }

            // Call main() directly, suppress stdout via output buffering
            ob_start();
            $exitCode = \main(['oasis-http-migrate-v3-check', $dir]);
            ob_end_clean();

            if ($hasRedFinding) {
                $this->assertSame(
                    1,
                    $exitCode,
                    'Exit code should be 1 when 🔴 findings exist'
                );
            } else {
                $this->assertSame(
                    0,
                    $exitCode,
                    'Exit code should be 0 when no 🔴 findings exist'
                );
            }
        });
    }

    // ─── Property 10: 输出格式有效性 ────────────────────────────────

    /**
     * Feature: php85-migration-guide, Property 10: 输出格式有效性
     *
     * For any set of Findings, when --format=json is specified, the output
     * SHALL be valid JSON, parseable as an array, and each element SHALL
     * contain the fields file, line, severity, issue, and action.
     *
     * 直接调用 scanPhpFile() + reportJson()，通过 output buffering 捕获
     * JSON 输出，避免 fork 进程。
     *
     * Ref: Requirement 14, AC 5/6; Design Correctness Property 10
     */
    public function testJsonOutputFormatValidity(): void
    {
        $rules = \getRules();

        $allUsePatterns = array_merge(
            self::REMOVED_CLASSES,
            self::CHANGED_APIS,
            self::OLD_EVENTS,
        );

        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::elements($allUsePatterns)
        )->then(function (string $className) use ($rules) {
            $dir = $this->createTempDir();
            $content = $this->generatePhpFileWithUse($className);
            $filePath = $dir . '/test.php';
            file_put_contents($filePath, $content);

            $findings = \scanPhpFile($filePath, $content, $rules, $dir);
            $this->assertNotEmpty($findings, "Should produce findings for {$className}");

            // Capture reportJson output via output buffering
            ob_start();
            \reportJson($findings, $dir);
            $jsonOutput = ob_get_clean();

            $decoded = json_decode($jsonOutput, true);
            $this->assertNotNull(
                $decoded,
                "Output must be valid JSON. Raw output: {$jsonOutput}"
            );
            $this->assertIsArray($decoded, 'JSON output must be an array');

            foreach ($decoded as $i => $element) {
                $this->assertArrayHasKey('file', $element, "Element #{$i} must have 'file'");
                $this->assertArrayHasKey('line', $element, "Element #{$i} must have 'line'");
                $this->assertArrayHasKey('severity', $element, "Element #{$i} must have 'severity'");
                $this->assertArrayHasKey('issue', $element, "Element #{$i} must have 'issue'");
                $this->assertArrayHasKey('action', $element, "Element #{$i} must have 'action'");
            }
        });
    }

    // ─── Property 11: 二进制文件与非 UTF-8 文件容错 ─────────────────

    /**
     * Feature: php85-migration-guide, Property 11: 二进制文件与非 UTF-8 文件容错
     *
     * For any directory containing a mix of valid PHP files, binary files,
     * and non-UTF-8 encoded files, the scanner SHALL complete without error,
     * skip non-scannable files, and still produce correct Findings for the
     * valid PHP files.
     *
     * 直接调用 scanDirectory()，避免 fork 进程。
     *
     * Ref: Requirement 15, AC 3; Design Correctness Property 11
     */
    public function testBinaryAndNonUtf8FileTolerance(): void
    {
        $rules = \getRules();

        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::choose(1, 3),
            Generators::choose(1, 3),
            Generators::choose(0, 2)
        )->then(function (int $phpCount, int $binaryCount, int $nonUtf8Count) use ($rules) {
            $dir = $this->createTempDir();
            $validPhpFiles = [];

            for ($i = 0; $i < $phpCount; $i++) {
                $className = self::REMOVED_CLASSES[$i % count(self::REMOVED_CLASSES)];
                $filename = "valid_{$i}.php";
                file_put_contents(
                    $dir . '/' . $filename,
                    $this->generatePhpFileWithUse($className)
                );
                $validPhpFiles[] = $filename;
            }

            for ($i = 0; $i < $binaryCount; $i++) {
                $binaryContent = random_bytes(256);
                file_put_contents($dir . "/binary_{$i}.php", $binaryContent);
            }

            for ($i = 0; $i < $nonUtf8Count; $i++) {
                $nonUtf8Content = "<?php\n// " . "\xC0\xC1\xF5\xF6\xF7\xF8" . "\necho 'test';\n";
                file_put_contents($dir . "/nonutf8_{$i}.php", $nonUtf8Content);
            }

            // Capture stderr from scanDirectory (it writes warnings to stderr)
            $findings = \scanDirectory($dir, $rules);

            // Findings should reference only valid PHP files
            $foundFiles = array_unique(array_column($findings, 'file'));
            foreach ($foundFiles as $file) {
                $basename = basename($file);
                $this->assertStringStartsWith(
                    'valid_',
                    $basename,
                    "Findings should only reference valid PHP files, got: {$file}"
                );
            }

            // All valid PHP files should have at least one finding
            foreach ($validPhpFiles as $phpFile) {
                $fileFindings = array_filter(
                    $findings,
                    fn(array $f) => basename($f['file']) === $phpFile
                );
                $this->assertNotEmpty(
                    $fileFindings,
                    "Valid PHP file '{$phpFile}' should have at least one finding"
                );
            }
        });
    }
}
