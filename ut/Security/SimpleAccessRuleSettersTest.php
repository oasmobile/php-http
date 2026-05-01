<?php
/**
 * Unit tests for SimpleAccessRule setter methods.
 *
 * Covers setPattern(), setRequiredRoles(), setRequiredChannel() — the uncovered setters.
 */

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\SimpleAccessRule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

class SimpleAccessRuleSettersTest extends TestCase
{
    private function createRule(): SimpleAccessRule
    {
        return new SimpleAccessRule([
            'pattern' => '/initial',
            'roles'   => ['ROLE_USER'],
            'channel' => null,
        ]);
    }

    /**
     * setPattern() with a string value should update the pattern.
     */
    public function testSetPatternWithString(): void
    {
        $rule = $this->createRule();
        $rule->setPattern('/new-pattern');

        $this->assertSame('/new-pattern', $rule->getPattern());
    }

    /**
     * setPattern() with a RequestMatcherInterface should update the pattern.
     */
    public function testSetPatternWithRequestMatcher(): void
    {
        $rule = $this->createRule();
        $matcher = new ChainRequestMatcher([new PathRequestMatcher('/api/.*')]);
        $rule->setPattern($matcher);

        $this->assertSame($matcher, $rule->getPattern());
    }

    /**
     * setRequiredRoles() should update the roles.
     */
    public function testSetRequiredRoles(): void
    {
        $rule = $this->createRule();
        $rule->setRequiredRoles(['ROLE_ADMIN', 'ROLE_SUPER']);

        $this->assertSame(['ROLE_ADMIN', 'ROLE_SUPER'], $rule->getRequiredRoles());
    }

    /**
     * setRequiredChannel() should update the channel.
     */
    public function testSetRequiredChannelToHttps(): void
    {
        $rule = $this->createRule();
        $rule->setRequiredChannel('https');

        $this->assertSame('https', $rule->getRequiredChannel());
    }

    /**
     * setRequiredChannel() with null should clear the channel.
     */
    public function testSetRequiredChannelToNull(): void
    {
        $rule = new SimpleAccessRule([
            'pattern' => '/test',
            'roles'   => ['ROLE_USER'],
            'channel' => 'https',
        ]);
        $rule->setRequiredChannel(null);

        $this->assertNull($rule->getRequiredChannel());
    }
}
