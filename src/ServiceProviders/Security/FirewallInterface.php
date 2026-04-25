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
    public function getPattern(): string|RequestMatcherInterface;

    /**
     * @return bool
     */
    public function isStateless(): bool;

    /**
     * @return array<string, mixed>    Array of policies
     *                  key is policy name,
     *                  and value is an option array or bool-true
     */
    public function getPolicies(): array;

    /**
     * @return array<string, mixed>|UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface>
     */
    public function getUserProvider(): array|UserProviderInterface;

    /**
     * @return array<string, mixed>    Other values to be merged to firewall setting
     */
    public function getOtherSettings(): array;

}
