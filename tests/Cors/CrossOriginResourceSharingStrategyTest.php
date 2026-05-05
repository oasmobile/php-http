<?php
declare(strict_types=1);

/**
 * Unit tests for CrossOriginResourceSharingStrategy.
 *
 * Covers: RequestMatcherInterface pattern, InvalidArgumentException for bad pattern,
 * matches() returning false, isCredentialsAllowed(), getMaxAge(), getAllowedHeaders(),
 * getExposedHeaders().
 */

namespace Oasis\Mlib\Http\Test\Cors;

use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CrossOriginResourceSharingStrategyTest extends TestCase
{
    /**
     * matches() returns false when the request does not match the pattern.
     */
    public function testMatchesReturnsFalseForNonMatchingRequest(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern' => '/api/.*',
            'origins' => ['*'],
        ]);

        $request = Request::create('/other/path');
        $this->assertFalse($strategy->matches($request));
    }

    /**
     * matches() returns true when the request matches the pattern.
     */
    public function testMatchesReturnsTrueForMatchingRequest(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern' => '/api/.*',
            'origins' => ['*'],
        ]);

        $request = Request::create('/api/users');
        $this->assertTrue($strategy->matches($request));
    }

    /**
     * isCredentialsAllowed() returns the configured value.
     */
    public function testIsCredentialsAllowed(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern'             => '*',
            'origins'             => ['*'],
            'credentials_allowed' => true,
        ]);

        $this->assertTrue($strategy->isCredentialsAllowed());
    }

    /**
     * getMaxAge() returns the configured value.
     */
    public function testGetMaxAge(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern' => '*',
            'origins' => ['*'],
            'max_age' => 3600,
        ]);

        $this->assertSame(3600, $strategy->getMaxAge());
    }

    /**
     * getAllowedHeaders() returns comma-separated headers.
     */
    public function testGetAllowedHeaders(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern' => '*',
            'origins' => ['*'],
            'headers' => ['X-Custom', 'Authorization'],
        ]);

        $this->assertSame('X-Custom, Authorization', $strategy->getAllowedHeaders());
    }

    /**
     * getExposedHeaders() returns comma-separated exposed headers.
     */
    public function testGetExposedHeaders(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern'         => '*',
            'origins'         => ['*'],
            'headers_exposed' => ['X-Total-Count', 'X-Request-Id'],
        ]);

        $this->assertSame('X-Total-Count, X-Request-Id', $strategy->getExposedHeaders());
    }

    /**
     * isOriginAllowed() returns false for a completely invalid origin string.
     */
    public function testIsOriginAllowedReturnsFalseForInvalidOrigin(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern' => '*',
            'origins' => ['example.com'],
        ]);

        // A string that doesn't match the domain pattern at all
        $this->assertFalse($strategy->isOriginAllowed('not a valid origin!!!'));
    }

    /**
     * isWildcardOriginAllowed() returns true when origins contains "*".
     */
    public function testIsWildcardOriginAllowed(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern' => '*',
            'origins' => ['*'],
        ]);

        $this->assertTrue($strategy->isWildcardOriginAllowed());
    }

    /**
     * isWildcardOriginAllowed() returns false when origins does not contain "*".
     */
    public function testIsWildcardOriginNotAllowed(): void
    {
        $strategy = new CrossOriginResourceSharingStrategy([
            'pattern' => '*',
            'origins' => ['example.com'],
        ]);

        $this->assertFalse($strategy->isWildcardOriginAllowed());
    }
}
