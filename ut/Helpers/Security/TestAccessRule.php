<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-15
 * Time: 00:02
 */

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\SimpleAccessRule;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

class TestAccessRule extends SimpleAccessRule
{
    /**
     * TestAccessRule constructor.
     *
     * @param string|RequestMatcherInterface $pattern
     * @param string|array      $roles
     * @param string|null       $channel
     */
    public function __construct($pattern, $roles, $channel = null)
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
