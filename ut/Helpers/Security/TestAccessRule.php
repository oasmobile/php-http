<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-15
 * Time: 00:02
 */

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\SimpleAccessRule;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

class TestAccessRule extends SimpleAccessRule
{
    /**
     * TestAccessRule constructor.
     *
     * @param string|RequestMatcherInterface $pattern
     * @param string|array      $roles
     * @param string|null       $channel
     */
    public function __construct(string|RequestMatcherInterface $pattern, string|array $roles, ?string $channel = null)
    {
        parent::__construct(
            [
                'pattern' => $pattern,
                'roles'   => $roles,
                'channel' => $channel,
            ]
        );
    }
}
