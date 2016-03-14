<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 23:37
 */

namespace Oasis\Mlib\Http\Ut\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\FirewallInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TestAuthenticationFirewall implements FirewallInterface
{
    
    /**
     * @return string
     */
    public function getPattern()
    {
        return "^/secured/madmin";
    }

    /**
     * @return bool
     */
    public function isStateless()
    {
        return true;
    }

    /**
     * @return array    Array of policies
     *                  key is policy name,
     *                  and value is an option array or bool-true
     */
    public function getPolicies()
    {
        return ['mauth' => true];
    }

    /**
     * @return array|UserProviderInterface
     */
    public function getUserProvider()
    {
        return new TestApiUserProvider();
    }

    /**
     * @return array    Other values to be merged to firewall setting
     */
    public function getOtherSettings()
    {
        return [];
    }
}
