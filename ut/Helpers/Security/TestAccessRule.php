<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-15
 * Time: 00:02
 */

namespace Oasis\Mlib\Http\Ut\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\SimpleAccessRule;

class TestAccessRule extends SimpleAccessRule
{
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
