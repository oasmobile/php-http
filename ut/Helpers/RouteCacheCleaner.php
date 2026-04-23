<?php
/**
 * Trait for cleaning route cache files before each test.
 *
 * The Symfony Router caches compiled matchers/generators as PHP files.
 * These cached files contain raw route defaults (including %param% placeholders)
 * baked in at dump time. When multiple test classes share the same cache_dir,
 * stale cache from a previous test can cause parameter replacement to be skipped
 * because the Router loads the cached matcher directly without calling
 * CacheableRouter::getRouteCollection().
 *
 * Any WebTestCase or TestCase that uses routing with a real cache_dir should
 * use this trait and call $this->cleanRouteCache($dir) in setUp().
 */

namespace Oasis\Mlib\Http\Test\Helpers;

trait RouteCacheCleaner
{
    /**
     * Remove all cached route matcher/generator PHP files from the given directory.
     *
     * @param string $cacheDir Absolute path to the cache directory
     */
    protected function cleanRouteCache($cacheDir)
    {
        if (!is_dir($cacheDir)) {
            return;
        }
        foreach (glob($cacheDir . '/Project*.php') as $file) {
            @unlink($file);
        }
        foreach (glob($cacheDir . '/Project*.php.meta') as $file) {
            @unlink($file);
        }
    }
}
