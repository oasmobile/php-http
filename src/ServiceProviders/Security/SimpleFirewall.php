<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-16
 * Time: 14:46
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\SimpleFirewallConfiguration;
use Oasis\Mlib\Utils\DataType;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SimpleFirewall implements FirewallInterface
{
    use ConfigurationValidationTrait;

    protected string|RequestMatcherInterface $pattern;
    /** @var array<string, mixed> */
    protected array $policies;
    /** @var array<string, mixed>|UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface> */
    protected array|UserProviderInterface $userProvider;
    /** @var array<string, mixed> */
    protected array $otherSettings;

    /**
     * @param array<string, mixed> $firewallConfiguration
     */
    public function __construct(array $firewallConfiguration)
    {
        $dp                  = $this->processConfiguration($firewallConfiguration, new SimpleFirewallConfiguration());
        $this->pattern       = $dp->getMandatory('pattern', DataType::Mixed);
        $this->policies      = $dp->getMandatory('policies', DataType::Array);
        $this->userProvider  = $dp->getMandatory('users', DataType::Mixed);
        $this->otherSettings = $dp->getMandatory('misc', DataType::Array);

    }

    /**
     * @return string|RequestMatcherInterface
     */
    public function getPattern(): string|RequestMatcherInterface
    {
        return $this->pattern;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPolicies(): array
    {
        return $this->policies;
    }

    /**
     * @return array<string, mixed>|UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface>
     */
    public function getUserProvider(): array|UserProviderInterface
    {
        return $this->userProvider;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOtherSettings(): array
    {
        return $this->otherSettings;
    }

}
