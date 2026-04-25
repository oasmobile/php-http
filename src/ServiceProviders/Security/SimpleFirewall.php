<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-16
 * Time: 14:46
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\SimpleFirewallConfiguration;
use Oasis\Mlib\Utils\DataProviderInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SimpleFirewall implements FirewallInterface
{
    use ConfigurationValidationTrait;

    protected string|RequestMatcherInterface $pattern;
    protected array $policies;
    protected array|UserProviderInterface $userProvider;
    protected bool $stateless;
    protected array $otherSettings;

    public function __construct(array $firewallConfiguration)
    {
        $dp                  = $this->processConfiguration($firewallConfiguration, new SimpleFirewallConfiguration());
        $this->pattern       = $dp->getMandatory('pattern', DataProviderInterface::MIXED_TYPE);
        $this->policies      = $dp->getMandatory('policies', DataProviderInterface::ARRAY_TYPE);
        $this->userProvider  = $dp->getMandatory('users', DataProviderInterface::MIXED_TYPE);
        $this->stateless     = $dp->getMandatory('stateless', DataProviderInterface::BOOL_TYPE);
        $this->otherSettings = $dp->getMandatory('misc', DataProviderInterface::ARRAY_TYPE);

    }

    /**
     * @return string|RequestMatcherInterface
     */
    public function getPattern(): string|RequestMatcherInterface
    {
        return $this->pattern;
    }

    /**
     * @return boolean
     */
    public function isStateless(): bool
    {
        return $this->stateless;
    }

    /**
     * @return array
     */
    public function getPolicies(): array
    {
        return $this->policies;
    }

    /**
     * @return array|UserProviderInterface
     */
    public function getUserProvider(): array|UserProviderInterface
    {
        return $this->userProvider;
    }

    /**
     * @return array
     */
    public function getOtherSettings(): array
    {
        return $this->otherSettings;
    }

}
