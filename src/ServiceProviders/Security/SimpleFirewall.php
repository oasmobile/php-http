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
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SimpleFirewall implements FirewallInterface
{
    use ConfigurationValidationTrait;

    /** @var  string */
    protected $pattern;
    /** @var  array */
    protected $policies;
    /** @var  array|UserProviderInterface */
    protected $userProvider;
    /** @var  bool */
    protected $stateless;
    /** @var  array */
    protected $otherSettings;

    public function __construct(array $firewallConfiguration)
    {
        $dp                  = $this->processConfiguration($firewallConfiguration, new SimpleFirewallConfiguration());
        $this->pattern       = $dp->getMandatory('pattern');
        $this->policies      = $dp->getMandatory('policies', DataProviderInterface::ARRAY_TYPE);
        $this->userProvider  = $dp->getMandatory('users', DataProviderInterface::MIXED_TYPE);
        $this->stateless     = $dp->getMandatory('stateless', DataProviderInterface::BOOL_TYPE);
        $this->otherSettings = $dp->getMandatory('misc', DataProviderInterface::ARRAY_TYPE);

    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @return boolean
     */
    public function isStateless()
    {
        return $this->stateless;
    }

    /**
     * @return array
     */
    public function getPolicies()
    {
        return $this->policies;
    }

    /**
     * @return array|UserProviderInterface
     */
    public function getUserProvider()
    {
        return $this->userProvider;
    }

    /**
     * @return array
     */
    public function getOtherSettings()
    {
        return $this->otherSettings;
    }

}
