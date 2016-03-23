<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 14:31
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

interface FirewallInterface
{
    /**
     * @return string|RequestMatcherInterface
     */
    public function getPattern();

    /**
     * @return bool
     */
    public function isStateless();

    /**
     * @return array    Array of policies
     *                  key is policy name,
     *                  and value is an option array or bool-true
     */
    public function getPolicies();

    /**
     * @return array|UserProviderInterface
     */
    public function getUserProvider();

    /**
     * @return array    Other values to be merged to firewall setting
     */
    public function getOtherSettings();

}
