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
 * 此阶段为 RED 状态——Check Script 尚不存在，测试预期全部 FAIL。
 *
 * Ref: Requirement 12, AC 1–8; Requirement 13, AC 2/3/6;
 *      Requirement 14, AC 5/6; Requirement 15, AC 3/4;
 *      Design Correctness Properties 5–11, Testing Strategy PBT
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

class MigrateCheckPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Path to the check script.
     */
    private const SCRIPT_PATH = __DIR__ . '/../../bin/oasis-http-migrate-v3-check';

    // ─── Rule definitions for generators ────────────────────────────

    /**
     * Removed_API class names — simple template-based generation.
     */
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

    /**
     * Changed_API interface/class names — simple template-based generation.
     */
    private const CHANGED_APIS = [
        'AuthenticationPolicyInterface',
        'FirewallInterface',
        'AccessRuleInterface',
        'AbstractSimplePreAuthenticator',
        'AbstractSimplePreAuthenticateUserProvider',
        'MiddlewareInterface',
        'ResponseRendererInterface',
    ];

    /**
     * Old Symfony event class names — simple template-based generation.
     */
    private const OLD_EVENTS = [
        'FilterResponseEvent',
        'GetResponseEvent',
        'GetResponseForExceptionEvent',
        'MASTER_REQUEST',
    ];

    /**
     * Removed packages for composer.json detection.
     */
    private const REMOVED_PACKAGES = [
        'silex/silex',
        'silex/providers',
        'twig/extensions',
    ];

    /**
     * Pimple access pattern snippets (CR Q4→C: complex patterns use predefined snippets).
     */
    private const PIMPLE_SNIPPETS = [
        '$app[\'db\']',
        '$app[\'session\']',
        '$app[\'twig\']',
        '$container[\'logger\']',
        '$container[\'mailer\']',
    ];

    /**
     * Guzzle 6.x pattern snippets (CR Q4→C: complex patterns use predefined snippets).
     */
    private const GUZZLE_SNIPPETS = [
        "'exceptions' => false",
        "'exceptions' => true",
    ];

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Create a temporary directory and return its path.
     * Automatically cleaned up in tearDown.
     */
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
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    /**
     * Generate a PHP file containing a use statement for the given class.
     * Simple rules use template concatenation (CR Q4→C).
     */
    private function generatePhpFileWithUse(string $className): string
    {
        $shortName = basename(str_replace('\\', '/', $className));

        return "<?php\n\nuse {$className};\n\nclass MyClass {\n    public function test(): void {\n        \$obj = new {$shortName}();\n    }\n}\n";
    }

    /**
     * Generate a PHP file containing a Pimple access snippet.
     * Complex patterns use predefined snippets (CR Q4→C).
     */
    private function generatePhpFileWithPimple(string $snippet): string
    {
        return "<?php\n\nclass MyService {\n    public function boot(\$app): void {\n        \$service = {$snippet};\n    }\n}\n";
    }

    /**
     * Generate a PHP file containing a Guzzle 6.x pattern snippet.
     * Complex patterns use predefined snippets (CR Q4→C).
     */
    private function generatePhpFileWithGuzzle(string $snippet): string
    {
        return "<?php\n\nuse GuzzleHttp\\Client;\n\n\$client = new Client([\n    {$snippet},\n]);\n";
    }

    /**
     * Generate a composer.json containing a removed package reference.
     */
    private function generateComposerJsonWithPackage(string $package): string
    {
        return json_encode([
            'require' => [
                $package => '^1.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Run the check script against a directory and return [exitCode, stdout, stderr].
     *
     * @return array{int, string, string}
     */
    private function runScript(string $directory, string $format = 'text'): array
    {
        $cmd = sprintf(
            'php %s --format=%s %s 2>&1',
            escapeshellarg(self::SCRIPT_PATH),
            escapeshellarg($format),
            escapeshellarg($directory)
        );

        // Use proc_open to capture stdout and stderr separately
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            sprintf(
                'php %s --format=%s %s',
                escapeshellarg(self::SCRIPT_PATH),
                escapeshellarg($format),
                escapeshellarg($directory)
            ),
            $descriptors,
            $pipes
        );

        $this->assertIsResource($process, 'Failed to start check script process');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }

    // ─── Property 5: 规则检测完整性 ─────────────────────────────────

    /**
     * Feature: php85-migration-guide, Property 5: 规则检测完整性
     *
     * For any PHP file containing a reference to a pattern registered in the
     * Rule Registry, the scanner SHALL produce at least one Finding for that file.
     *
     * Generator strategy (CR Q4→C):
     * - Simple rules (Removed_API, Changed_API, old events): template concatenation
     * - Complex patterns (Pimple access, Guzzle options): predefined snippets
     *
     * Ref: Requirement 12, AC 3–8; Design Correctness Property 5
     */
    public function testRuleDetectionCompleteness(): void
    {
        // Combine all detectable patterns into a single generator
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
        )->withMaxSize(count($allPatterns))->then(function (array $pattern) {
            $dir = $this->createTempDir();

            switch ($pattern['type']) {
                case 'use':
                    $content = $this->generatePhpFileWithUse($pattern['value']);
                    file_put_contents($dir . '/test.php', $content);
                    break;
                case 'pimple':
                    $content = $this->generatePhpFileWithPimple($pattern['value']);
                    file_put_contents($dir . '/test.php', $content);
                    break;
                case 'guzzle':
                    $content = $this->generatePhpFileWithGuzzle($pattern['value']);
                    file_put_contents($dir . '/test.php', $content);
                    break;
                case 'composer':
                    $content = $this->generateComposerJsonWithPackage($pattern['value']);
                    file_put_contents($dir . '/composer.json', $content);
                    break;
            }

            [$exitCode, $stdout, $stderr] = $this->runScript($dir, 'json');

            // Script should produce valid JSON output
            $findings = json_decode($stdout, true);
            $this->assertIsArray(
                $findings,
                sprintf(
                    "Scanner output should be valid JSON for pattern '%s' (type: %s). stdout: %s, stderr: %s",
                    $pattern['value'],
                    $pattern['type'],
                    $stdout,
                    $stderr
                )
            );

            // At least one finding should be produced
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
     * Ref: Requirement 12, AC 2; Design Correctness Property 6
     */
    public function testRecursiveScanCompleteness(): void
    {
        $this->minimumEvaluationRatio(0.5)->forAll(
            // depth: 1–5 levels
            Generators::choose(1, 5),
            // file count per level: 1–3
            Generators::choose(1, 3)
        )->then(function (int $depth, int $filesPerLevel) {
            $dir = $this->createTempDir();
            $placedFiles = 0;

            // Build nested directory structure and place PHP files with detectable patterns
            $currentPath = $dir;
            for ($level = 0; $level < $depth; $level++) {
                $subDir = $currentPath . '/level' . $level;
                mkdir($subDir, 0777, true);
                $currentPath = $subDir;

                for ($f = 0; $f < $filesPerLevel; $f++) {
                    // Use a Removed_API reference so the file produces findings
                    $className = self::REMOVED_CLASSES[$placedFiles % count(self::REMOVED_CLASSES)];
                    $content = $this->generatePhpFileWithUse($className);
                    file_put_contents($currentPath . "/file{$f}.php", $content);
                    $placedFiles++;
                }
            }

            [$exitCode, $stdout, $stderr] = $this->runScript($dir, 'json');

            $findings = json_decode($stdout, true);
            $this->assertIsArray($findings, "Scanner output should be valid JSON. stderr: {$stderr}");

            // Count unique files in findings
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
     * Reuses Property 5 generator.
     *
     * Ref: Requirement 13, AC 2; Design Correctness Property 7
     */
    public function testFindingFieldCompleteness(): void
    {
        $simplePatterns = array_merge(
            self::REMOVED_CLASSES,
            self::CHANGED_APIS,
            self::OLD_EVENTS,
        );

        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::elements($simplePatterns)
        )->then(function (string $className) {
            $dir = $this->createTempDir();
            $content = $this->generatePhpFileWithUse($className);
            file_put_contents($dir . '/test.php', $content);

            [$exitCode, $stdout, $stderr] = $this->runScript($dir, 'json');

            $findings = json_decode($stdout, true);
            $this->assertIsArray($findings, "Scanner output should be valid JSON. stderr: {$stderr}");
            $this->assertNotEmpty($findings, "Should produce at least one finding for {$className}");

            foreach ($findings as $i => $finding) {
                // file: relative path (string, non-empty)
                $this->assertArrayHasKey('file', $finding, "Finding #{$i} must have 'file' field");
                $this->assertIsString($finding['file'], "Finding #{$i} 'file' must be a string");
                $this->assertNotEmpty($finding['file'], "Finding #{$i} 'file' must be non-empty");

                // line: positive integer
                $this->assertArrayHasKey('line', $finding, "Finding #{$i} must have 'line' field");
                $this->assertIsInt($finding['line'], "Finding #{$i} 'line' must be an integer");
                $this->assertGreaterThan(0, $finding['line'], "Finding #{$i} 'line' must be positive");

                // issue: non-empty string
                $this->assertArrayHasKey('issue', $finding, "Finding #{$i} must have 'issue' field");
                $this->assertIsString($finding['issue'], "Finding #{$i} 'issue' must be a string");
                $this->assertNotEmpty($finding['issue'], "Finding #{$i} 'issue' must be non-empty");

                // action: non-empty string
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
     * Ref: Requirement 13, AC 3; Design Correctness Property 8
     */
    public function testSeverityGroupOrdering(): void
    {
        // We need files that trigger different severity levels:
        // 🔴: Removed_API / Changed_API / old events
        // 🟡: Guzzle 6.x patterns
        // To get 🟢 we would need patterns that produce 🟢 findings — but per the design,
        // all current rules are 🔴 or 🟡. We test the ordering of 🔴 before 🟡.
        $this->minimumEvaluationRatio(0.5)->forAll(
            // Pick a random Removed_API (🔴)
            Generators::elements(self::REMOVED_CLASSES),
            // Pick a random Guzzle snippet (🟡)
            Generators::elements(self::GUZZLE_SNIPPETS)
        )->then(function (string $removedClass, string $guzzleSnippet) {
            $dir = $this->createTempDir();

            // File with 🔴 finding
            file_put_contents(
                $dir . '/red_finding.php',
                $this->generatePhpFileWithUse($removedClass)
            );

            // File with 🟡 finding
            file_put_contents(
                $dir . '/yellow_finding.php',
                $this->generatePhpFileWithGuzzle($guzzleSnippet)
            );

            [$exitCode, $stdout, $stderr] = $this->runScript($dir, 'text');

            // Find positions of severity section headers in text output
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
     * Ref: Requirement 13, AC 6; Design Correctness Property 9
     */
    public function testExitCodeCorrectness(): void
    {
        $this->minimumEvaluationRatio(0.5)->forAll(
            // Scenario: true = has 🔴 findings, false = only 🟡 findings
            Generators::elements([true, false])
        )->then(function (bool $hasRedFinding) {
            $dir = $this->createTempDir();

            if ($hasRedFinding) {
                // Place a file with a Removed_API reference (🔴)
                $className = self::REMOVED_CLASSES[array_rand(self::REMOVED_CLASSES)];
                file_put_contents(
                    $dir . '/test.php',
                    $this->generatePhpFileWithUse($className)
                );
            } else {
                // Place a file with only a Guzzle 6.x pattern (🟡)
                $snippet = self::GUZZLE_SNIPPETS[array_rand(self::GUZZLE_SNIPPETS)];
                file_put_contents(
                    $dir . '/test.php',
                    $this->generatePhpFileWithGuzzle($snippet)
                );
            }

            [$exitCode, $stdout, $stderr] = $this->runScript($dir);

            if ($hasRedFinding) {
                $this->assertSame(
                    1,
                    $exitCode,
                    "Exit code should be 1 when 🔴 findings exist. stdout: {$stdout}"
                );
            } else {
                $this->assertSame(
                    0,
                    $exitCode,
                    "Exit code should be 0 when no 🔴 findings exist. stdout: {$stdout}"
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
     * Reuses Property 5 generator.
     *
     * Ref: Requirement 14, AC 5/6; Design Correctness Property 10
     */
    public function testJsonOutputFormatValidity(): void
    {
        $allUsePatterns = array_merge(
            self::REMOVED_CLASSES,
            self::CHANGED_APIS,
            self::OLD_EVENTS,
        );

        $this->minimumEvaluationRatio(0.5)->forAll(
            Generators::elements($allUsePatterns)
        )->then(function (string $className) {
            $dir = $this->createTempDir();
            file_put_contents(
                $dir . '/test.php',
                $this->generatePhpFileWithUse($className)
            );

            [$exitCode, $stdout, $stderr] = $this->runScript($dir, 'json');

            // Output must be valid JSON
            $decoded = json_decode($stdout, true);
            $this->assertNotNull(
                $decoded,
                "Output must be valid JSON. Raw output: {$stdout}, stderr: {$stderr}"
            );
            $this->assertIsArray($decoded, 'JSON output must be an array');

            // Each element must have the required fields
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
     * Ref: Requirement 15, AC 3; Design Correctness Property 11
     */
    public function testBinaryAndNonUtf8FileTolerance(): void
    {
        $this->minimumEvaluationRatio(0.5)->forAll(
            // Number of valid PHP files (1–3)
            Generators::choose(1, 3),
            // Number of binary files (1–3)
            Generators::choose(1, 3),
            // Number of non-UTF-8 files (0–2)
            Generators::choose(0, 2)
        )->then(function (int $phpCount, int $binaryCount, int $nonUtf8Count) {
            $dir = $this->createTempDir();
            $validPhpFiles = [];

            // Place valid PHP files with detectable patterns
            for ($i = 0; $i < $phpCount; $i++) {
                $className = self::REMOVED_CLASSES[$i % count(self::REMOVED_CLASSES)];
                $filename = "valid_{$i}.php";
                file_put_contents(
                    $dir . '/' . $filename,
                    $this->generatePhpFileWithUse($className)
                );
                $validPhpFiles[] = $filename;
            }

            // Place binary files (with .php extension to test that they are skipped)
            for ($i = 0; $i < $binaryCount; $i++) {
                $binaryContent = random_bytes(256);
                file_put_contents($dir . "/binary_{$i}.php", $binaryContent);
            }

            // Place non-UTF-8 files (with .php extension)
            for ($i = 0; $i < $nonUtf8Count; $i++) {
                // Latin-1 encoded content with PHP opening tag
                $nonUtf8Content = "<?php\n// " . "\xC0\xC1\xF5\xF6\xF7\xF8" . "\necho 'test';\n";
                file_put_contents($dir . "/nonutf8_{$i}.php", $nonUtf8Content);
            }

            [$exitCode, $stdout, $stderr] = $this->runScript($dir, 'json');

            // Script should not crash (exit code should not be 2 = input error)
            $this->assertNotSame(
                2,
                $exitCode,
                "Script should not crash on binary/non-UTF-8 files. stderr: {$stderr}"
            );

            // Output should be valid JSON
            $findings = json_decode($stdout, true);
            $this->assertIsArray(
                $findings,
                "Output should be valid JSON even with binary files present. stdout: {$stdout}"
            );

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
