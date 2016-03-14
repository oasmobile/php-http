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
    
    public function getPattern()
    {
        return "^/secured/madmin";
    }

    public function getRequiredRoles()
    {
        return "ROLE_ADMIN";
    }

    public function getRequiredChannel()
    {
        return null;
    }
}
