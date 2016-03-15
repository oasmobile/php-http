<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-15
 * Time: 00:02
 */

namespace Oasis\Mlib\Http\Ut\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AccessRuleInterface;

class TestAccessRule implements AccessRuleInterface
{
    protected $pattern;
    protected $roles;
    protected $channel;

    public function __construct($pattern, $roles, $channel = null)
    {
        $this->pattern = $pattern;
        $this->roles   = $roles;
        $this->channel = $channel;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    public function getRequiredRoles()
    {
        return $this->roles;
    }

    public function getRequiredChannel()
    {
        return $this->channel;
    }
}
