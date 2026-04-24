<?php
/**
 * Trait for providing isolated cache directories per test class.
 *
 * The Symfony Kernel caches compiled container and route matchers keyed by
 * environment + debug flag. When multiple test classes share the same cache_dir
 * but use different route files, stale cache causes incorrect behavior.
 *
 * Each test class gets its own isolated temp cache directory via
 * createTempCacheDir(). The directory is automatically cleaned up
 * in tearDownAfterClass().
 */

namespace Oasis\Mlib\Http\Test\Helpers;

trait RouteCacheCleaner
{
    /** @var string|null Isolated temp cache dir for this test class */
    private static $tempCacheDir;

    /**
     * Create (or return) an isolated temp cache directory for this test class.
     * Returns the same directory across all calls within the same class + process.
     *
     * @return string Absolute path to the isolated cache directory
     */
    protected static function createTempCacheDir(): string
    {
        if (self::$tempCacheDir === null) {
            self::$tempCacheDir = sys_get_temp_dir() . '/oasis-http-test-' . md5(static::class) . '-' . getmypid();
            if (!is_dir(self::$tempCacheDir)) {
                mkdir(self::$tempCacheDir, 0777, true);
            }
        }

        return self::$tempCacheDir;
    }

    /**
     * @afterClass
     */
    public static function cleanUpTempCacheDir(): void
    {
        if (self::$tempCacheDir !== null && is_dir(self::$tempCacheDir)) {
            self::removeDirRecursive(self::$tempCacheDir);
            self::$tempCacheDir = null;
        }
    }

    /**
     * Backward-compatible method that returns the isolated temp cache dir.
     * Callers that previously passed a shared cache_dir now get an isolated one.
     *
     * @param string $cacheDir Ignored (kept for backward compatibility)
     */
    protected function cleanRouteCache($cacheDir = '')
    {
        // Return value not used by callers (they call this for side effects).
        // The actual isolation happens via createTempCacheDir() in bootstrap files.
    }

    private static function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                self::removeDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
