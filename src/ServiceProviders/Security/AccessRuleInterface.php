<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 23:58
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

interface AccessRuleInterface
{
    public function getPattern();

    public function getRequiredRoles();

    public function getRequiredChannel();
}
