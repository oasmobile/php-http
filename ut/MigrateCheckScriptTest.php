<?php
/**
 * Unit Tests for Check Script (bin/oasis-http-migrate-v3-check).
 *
 * Feature: php85-migration-guide
 *
 * 覆盖 Check Script 的 CLI 行为、错误处理、各类规则检测的具体示例。
 * 此阶段为 RED 状态——Check Script 尚不存在，测试预期全部 FAIL。
 *
 * Ref: Requirement 12, AC 1–8; Requirement 13, AC 1–6;
 *      Requirement 14, AC 4/5/6; Requirement 15, AC 1–5;
 *      Design Error Handling, Testing Strategy Unit Tests
 */

namespace Oasis\Mlib\Http\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MigrateCheckScriptTest extends TestCase
{
    /**
     * Path to the check script.
     */
    private const SCRIPT_PATH = __DIR__ . '/../bin/oasis-http-migrate-v3-check';

    /**
     * Temporary directories created during tests, cleaned up in tearDown.
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

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/migrate-check-unit-' . bin2hex(random_bytes(8));
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

    /**
     * Run the check script and return [exitCode, stdout, stderr].
     *
     * @param list<string> $args  Command-line arguments (after the script name).
     *
     * @return array{int, string, string}
     */
    private function runScript(array $args = []): array
    {
        $cmd = 'php ' . escapeshellarg(self::SCRIPT_PATH);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        $this->assertIsResource($process, 'Failed to start check script process');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }

    /**
     * Write a PHP file into the given directory and return its path.
     */
    private function writePhpFile(string $dir, string $filename, string $content): string
    {
        $path = $dir . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Write a composer.json file into the given directory.
     */
    private function writeComposerJson(string $dir, array $require, array $requireDev = []): string
    {
        $data = [];
        if ($require) {
            $data['require'] = $require;
        }
        if ($requireDev) {
            $data['require-dev'] = $requireDev;
        }
        $path = $dir . '/composer.json';
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    // ═════════════════════════════════════════════════════════════════
    // CLI 行为与错误处理
    // Ref: Requirement 14, AC 4; Requirement 15, AC 1/2; Design Error Handling
    // ═════════════════════════════════════════════════════════════════

    /**
     * 目录不存在 → stderr 输出错误信息 + exit code 2.
     *
     * Ref: R15 AC1; Design Error Handling "目标目录不存在"
     */
    public function testNonExistentDirectoryReturnsExitCode2(): void
    {
        $nonExistent = sys_get_temp_dir() . '/migrate-check-nonexistent-' . bin2hex(random_bytes(8));

        [$exitCode, $stdout, $stderr] = $this->runScript([$nonExistent]);

        $this->assertSame(2, $exitCode, 'Exit code should be 2 for non-existent directory');
        $this->assertNotEmpty($stderr, 'stderr should contain an error message');
        $this->assertStringContainsString(
            'does not exist',
            $stderr,
            'Error message should mention directory does not exist'
        );
    }

    /**
     * 空目录（无 .php 文件）→ 提示信息 + exit code 0.
     *
     * Ref: R15 AC2; Design Error Handling "目标目录无 .php 文件"
     */
    public function testEmptyDirectoryReturnsExitCode0(): void
    {
        $dir = $this->createTempDir();

        [$exitCode, $stdout, $stderr] = $this->runScript([$dir]);

        $this->assertSame(0, $exitCode, 'Exit code should be 0 for empty directory');
        $this->assertStringContainsString(
            'No PHP files found',
            $stdout . $stderr,
            'Output should mention no PHP files found'
        );
    }

    /**
     * --help 选项 → 输出 usage 帮助信息.
     *
     * Ref: R14 AC4; Design Error Handling "无命令行参数"
     */
    public function testHelpOptionDisplaysUsage(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runScript(['--help']);

        $combined = $stdout . $stderr;
        $this->assertStringContainsString('Usage:', $combined, 'Help output should contain Usage:');
        $this->assertStringContainsString('--help', $combined, 'Help output should mention --help');
        $this->assertStringContainsString('--format', $combined, 'Help output should mention --format');
    }

    /**
     * 无效 --format 值 → stderr 输出错误信息 + exit code 2.
     *
     * Ref: R14 AC5; Design Error Handling "无效的 --format 值"
     */
    public function testInvalidFormatReturnsExitCode2(): void
    {
        $dir = $this->createTempDir();

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=invalid', $dir]);

        $this->assertSame(2, $exitCode, 'Exit code should be 2 for invalid format');
        $this->assertNotEmpty($stderr, 'stderr should contain an error message');
        $this->assertStringContainsString(
            'Unsupported format',
            $stderr,
            'Error message should mention unsupported format'
        );
    }

    // ═════════════════════════════════════════════════════════════════
    // 健壮性
    // Ref: Requirement 15, AC 3–5; Design Error Handling
    // ═════════════════════════════════════════════════════════════════

    /**
     * 符号链接循环处理 → 不崩溃，正常完成扫描.
     *
     * Ref: R15 AC4; Design Error Handling "符号链接循环"
     */
    public function testSymlinkLoopDoesNotCrash(): void
    {
        $dir = $this->createTempDir();

        // Place a detectable PHP file
        $this->writePhpFile($dir, 'test.php', "<?php\nuse SilexKernel;\n");

        // Create a symlink loop: dir/loop -> dir
        $linkPath = $dir . '/loop';
        if (!@symlink($dir, $linkPath)) {
            $this->markTestSkipped('Cannot create symlinks on this system');
        }

        [$exitCode, $stdout, $stderr] = $this->runScript([$dir]);

        // Should not crash (exit code 2 = input error, which would indicate a crash)
        $this->assertNotSame(
            2,
            $exitCode,
            "Script should not crash on symlink loops. stderr: {$stderr}"
        );
    }

    /**
     * 文件权限错误 → warning 到 stderr + 继续扫描其他文件.
     *
     * Ref: R15 AC5; Design Error Handling "文件无读取权限"
     */
    public function testUnreadableFileWarningAndContinue(): void
    {
        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test permission errors as root');
        }

        $dir = $this->createTempDir();

        // Place a readable PHP file with a detectable pattern
        $this->writePhpFile($dir, 'readable.php', "<?php\nuse SilexKernel;\n");

        // Place an unreadable PHP file
        $unreadable = $this->writePhpFile($dir, 'unreadable.php', "<?php\nuse SilexKernel;\n");
        chmod($unreadable, 0000);

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        // Should produce a warning about the unreadable file
        $this->assertStringContainsString(
            'Cannot read',
            $stderr,
            'stderr should contain a warning about unreadable file'
        );

        // Should still produce findings for the readable file
        $findings = json_decode($stdout, true);
        $this->assertIsArray($findings, "Output should be valid JSON. stdout: {$stdout}");
        $this->assertNotEmpty($findings, 'Should still produce findings for readable files');

        // Restore permissions for cleanup
        chmod($unreadable, 0644);
    }

    // ═════════════════════════════════════════════════════════════════
    // 退出码逻辑
    // Ref: Requirement 13, AC 5/6
    // ═════════════════════════════════════════════════════════════════

    /**
     * 无 🔴 finding → exit code 0.
     *
     * Ref: R13 AC6; Design Data Models 退出码模型
     */
    public function testExitCode0WhenNoRedFindings(): void
    {
        $dir = $this->createTempDir();

        // Place a file with only a 🟡 pattern (Guzzle 6.x option)
        $this->writePhpFile(
            $dir,
            'guzzle.php',
            "<?php\n\$client = new \\GuzzleHttp\\Client([\n    'exceptions' => false,\n]);\n"
        );

        [$exitCode, $stdout, $stderr] = $this->runScript([$dir]);

        $this->assertSame(0, $exitCode, "Exit code should be 0 when no 🔴 findings. stdout: {$stdout}");
    }

    /**
     * 有 🔴 finding → exit code 1.
     *
     * Ref: R13 AC6; Design Data Models 退出码模型
     */
    public function testExitCode1WhenRedFindingsExist(): void
    {
        $dir = $this->createTempDir();

        // Place a file with a 🔴 pattern (Removed_API)
        $this->writePhpFile($dir, 'kernel.php', "<?php\nuse SilexKernel;\n");

        [$exitCode, $stdout, $stderr] = $this->runScript([$dir]);

        $this->assertSame(1, $exitCode, "Exit code should be 1 when 🔴 findings exist. stdout: {$stdout}");
    }

    // ═════════════════════════════════════════════════════════════════
    // Removed_API 引用检测
    // Ref: Requirement 12, AC 3; Design Rule Registry Removed_API 规则
    // ═════════════════════════════════════════════════════════════════

    #[DataProvider('removedApiProvider')]
    public function testDetectsRemovedApiReference(string $className, string $ruleId): void
    {
        $dir = $this->createTempDir();

        $shortName = basename(str_replace('\\', '/', $className));
        $this->writePhpFile(
            $dir,
            'test.php',
            "<?php\nuse {$className};\n\$obj = new {$shortName}();\n"
        );

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        $findings = json_decode($stdout, true);
        $this->assertIsArray($findings, "Output should be valid JSON. stderr: {$stderr}");
        $this->assertNotEmpty(
            $findings,
            "Should detect Removed_API reference: {$className}"
        );

        // Verify at least one finding references the expected class
        $issueTexts = array_column($findings, 'issue');
        $matched = false;
        foreach ($issueTexts as $issue) {
            if (stripos($issue, $shortName) !== false || stripos($issue, $className) !== false) {
                $matched = true;
                break;
            }
        }
        $this->assertTrue($matched, "Finding issue should mention '{$className}' or '{$shortName}'");
    }

    public static function removedApiProvider(): array
    {
        return [
            'SilexKernel'                        => ['SilexKernel', 'removed-silex-kernel'],
            'Silex\\Application'                 => ['Silex\\Application', 'removed-silex-app'],
            'Pimple\\Container'                  => ['Pimple\\Container', 'removed-pimple-container'],
            'Pimple\\ServiceProviderInterface'    => ['Pimple\\ServiceProviderInterface', 'removed-pimple-provider'],
            'Silex\\Api\\BootableProviderInterface' => ['Silex\\Api\\BootableProviderInterface', 'removed-bootable-provider'],
            'Twig_Environment'                   => ['Twig_Environment', 'removed-twig-env'],
            'Twig_SimpleFunction'                => ['Twig_SimpleFunction', 'removed-twig-func'],
            'Twig_Error_Loader'                  => ['Twig_Error_Loader', 'removed-twig-error'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // Changed_API 引用检测
    // Ref: Requirement 12, AC 4; Design Rule Registry Changed_API 规则
    // ═════════════════════════════════════════════════════════════════

    #[DataProvider('changedApiProvider')]
    public function testDetectsChangedApiReference(string $className, string $ruleId): void
    {
        $dir = $this->createTempDir();

        $this->writePhpFile(
            $dir,
            'test.php',
            "<?php\nuse {$className};\n\nclass MyImpl implements {$className} {}\n"
        );

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        $findings = json_decode($stdout, true);
        $this->assertIsArray($findings, "Output should be valid JSON. stderr: {$stderr}");
        $this->assertNotEmpty(
            $findings,
            "Should detect Changed_API reference: {$className}"
        );
    }

    public static function changedApiProvider(): array
    {
        return [
            'AuthenticationPolicyInterface'            => ['AuthenticationPolicyInterface', 'changed-auth-policy'],
            'FirewallInterface'                        => ['FirewallInterface', 'changed-firewall'],
            'AccessRuleInterface'                      => ['AccessRuleInterface', 'changed-access-rule'],
            'AbstractSimplePreAuthenticator'            => ['AbstractSimplePreAuthenticator', 'changed-pre-auth'],
            'AbstractSimplePreAuthenticateUserProvider' => ['AbstractSimplePreAuthenticateUserProvider', 'changed-pre-auth-user'],
            'MiddlewareInterface'                      => ['MiddlewareInterface', 'changed-middleware'],
            'ResponseRendererInterface'                => ['ResponseRendererInterface', 'changed-renderer'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // Pimple 访问模式检测
    // Ref: Requirement 12, AC 5; Design Rule Registry Pimple 模式规则
    // ═════════════════════════════════════════════════════════════════

    #[DataProvider('pimpleAccessProvider')]
    public function testDetectsPimpleAccessPattern(string $varName, string $key): void
    {
        $dir = $this->createTempDir();

        $this->writePhpFile(
            $dir,
            'test.php',
            "<?php\nfunction boot({$varName}): void {\n    \$service = {$varName}['{$key}'];\n}\n"
        );

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        $findings = json_decode($stdout, true);
        $this->assertIsArray($findings, "Output should be valid JSON. stderr: {$stderr}");
        $this->assertNotEmpty(
            $findings,
            "Should detect Pimple access pattern: {$varName}['{$key}']"
        );
    }

    public static function pimpleAccessProvider(): array
    {
        return [
            '$app[\'db\']'        => ['$app', 'db'],
            '$app[\'session\']'   => ['$app', 'session'],
            '$app[\'twig\']'      => ['$app', 'twig'],
            '$container[\'logger\']' => ['$container', 'logger'],
            '$container[\'mailer\']' => ['$container', 'mailer'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // 旧 Symfony 事件类检测
    // Ref: Requirement 12, AC 6; Design Rule Registry 旧 Symfony 事件类规则
    // ═════════════════════════════════════════════════════════════════

    #[DataProvider('oldEventClassProvider')]
    public function testDetectsOldSymfonyEventClass(string $className): void
    {
        $dir = $this->createTempDir();

        $this->writePhpFile(
            $dir,
            'test.php',
            "<?php\nuse {$className};\n\nfunction handle({$className} \$event): void {}\n"
        );

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        $findings = json_decode($stdout, true);
        $this->assertIsArray($findings, "Output should be valid JSON. stderr: {$stderr}");
        $this->assertNotEmpty(
            $findings,
            "Should detect old Symfony event class: {$className}"
        );
    }

    public static function oldEventClassProvider(): array
    {
        return [
            'FilterResponseEvent'          => ['FilterResponseEvent'],
            'GetResponseEvent'             => ['GetResponseEvent'],
            'GetResponseForExceptionEvent' => ['GetResponseForExceptionEvent'],
            'MASTER_REQUEST'               => ['MASTER_REQUEST'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // composer.json 中旧包引用检测
    // Ref: Requirement 12, AC 7; Design Rule Registry 旧包引用规则
    // ═════════════════════════════════════════════════════════════════

    #[DataProvider('removedPackageProvider')]
    public function testDetectsRemovedPackageInComposerJson(string $package): void
    {
        $dir = $this->createTempDir();

        $this->writeComposerJson($dir, [$package => '^2.0']);

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        $findings = json_decode($stdout, true);
        $this->assertIsArray($findings, "Output should be valid JSON. stderr: {$stderr}");
        $this->assertNotEmpty(
            $findings,
            "Should detect removed package in composer.json: {$package}"
        );

        // Verify finding mentions the package name
        $issueTexts = array_column($findings, 'issue');
        $matched = false;
        foreach ($issueTexts as $issue) {
            if (stripos($issue, $package) !== false) {
                $matched = true;
                break;
            }
        }
        $this->assertTrue($matched, "Finding issue should mention package '{$package}'");
    }

    /**
     * Also test detection in require-dev section.
     */
    public function testDetectsRemovedPackageInRequireDev(): void
    {
        $dir = $this->createTempDir();

        $this->writeComposerJson($dir, [], ['silex/silex' => '^2.3']);

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        $findings = json_decode($stdout, true);
        $this->assertIsArray($findings, "Output should be valid JSON. stderr: {$stderr}");
        $this->assertNotEmpty(
            $findings,
            'Should detect removed package in require-dev section'
        );
    }

    public static function removedPackageProvider(): array
    {
        return [
            'silex/silex'     => ['silex/silex'],
            'silex/providers' => ['silex/providers'],
            'twig/extensions' => ['twig/extensions'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════
    // Guzzle 6.x 模式检测
    // Ref: Requirement 12, AC 8; Design Rule Registry Guzzle 6.x 模式规则
    // ═════════════════════════════════════════════════════════════════

    /**
     * 检测 'exceptions' => false 模式.
     *
     * Ref: R12 AC8; Design CR Q4→B
     */
    public function testDetectsGuzzleExceptionsOption(): void
    {
        $dir = $this->createTempDir();

        $this->writePhpFile(
            $dir,
            'http_client.php',
            "<?php\nuse GuzzleHttp\\Client;\n\n\$client = new Client([\n    'exceptions' => false,\n]);\n"
        );

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        $findings = json_decode($stdout, true);
        $this->assertIsArray($findings, "Output should be valid JSON. stderr: {$stderr}");
        $this->assertNotEmpty(
            $findings,
            "Should detect Guzzle 6.x 'exceptions' option pattern"
        );
    }

    // ═════════════════════════════════════════════════════════════════
    // 输出格式
    // Ref: Requirement 13, AC 1/4/5; Requirement 14, AC 5/6
    // ═════════════════════════════════════════════════════════════════

    /**
     * 无问题时输出 success message.
     *
     * Ref: R13 AC5; Design Reporter
     */
    public function testSuccessMessageWhenNoFindings(): void
    {
        $dir = $this->createTempDir();

        // Place a clean PHP file with no detectable patterns
        $this->writePhpFile(
            $dir,
            'clean.php',
            "<?php\n\nfunction hello(): string {\n    return 'world';\n}\n"
        );

        [$exitCode, $stdout, $stderr] = $this->runScript([$dir]);

        $this->assertSame(0, $exitCode, 'Exit code should be 0 when no issues found');
        $this->assertMatchesRegularExpression(
            '/compatible|no.*(?:issues?|findings?|problems?)/i',
            $stdout . $stderr,
            'Output should contain a success/compatibility message'
        );
    }

    /**
     * --format=json 输出有效 JSON.
     *
     * Ref: R14 AC5/6; Design Reporter JSON 格式
     */
    public function testJsonFormatOutputIsValidJson(): void
    {
        $dir = $this->createTempDir();

        // Place files with mixed patterns to produce findings
        $this->writePhpFile($dir, 'kernel.php', "<?php\nuse SilexKernel;\n");
        $this->writePhpFile(
            $dir,
            'guzzle.php',
            "<?php\n\$client = new \\GuzzleHttp\\Client(['exceptions' => false]);\n"
        );

        [$exitCode, $stdout, $stderr] = $this->runScript(['--format=json', $dir]);

        $decoded = json_decode($stdout, true);
        $this->assertNotNull($decoded, "JSON output should be parseable. Raw: {$stdout}");
        $this->assertIsArray($decoded, 'JSON output should be an array');

        // Each element should have the required fields
        foreach ($decoded as $i => $element) {
            $this->assertArrayHasKey('file', $element, "Element #{$i} must have 'file'");
            $this->assertArrayHasKey('line', $element, "Element #{$i} must have 'line'");
            $this->assertArrayHasKey('severity', $element, "Element #{$i} must have 'severity'");
            $this->assertArrayHasKey('issue', $element, "Element #{$i} must have 'issue'");
            $this->assertArrayHasKey('action', $element, "Element #{$i} must have 'action'");
        }
    }
}
