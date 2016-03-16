<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-16
 * Time: 15:57
 */

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\SimpleAccessRuleConfiguration;
use Oasis\Mlib\Utils\DataProviderInterface;

class SimpleAccessRule implements AccessRuleInterface
{
    use ConfigurationValidationTrait;

    /** @var  string */
    protected $pattern;
    /** @var  array */
    protected $requiredRoles;
    /** @var  string|null */
    protected $requiredChannel;

    public function __construct(array $ruleConfiguration)
    {
        $dp = $this->processConfiguration($ruleConfiguration, new SimpleAccessRuleConfiguration());

        $this->pattern         = $dp->getMandatory('pattern');
        $this->requiredRoles   = $dp->getMandatory('roles', DataProviderInterface::ARRAY_TYPE);
        $this->requiredChannel = $dp->getOptional('channel', DataProviderInterface::STRING_TYPE);
    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @param string $pattern
     */
    public function setPattern($pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @return array
     */
    public function getRequiredRoles()
    {
        return $this->requiredRoles;
    }

    /**
     * @param array $requiredRoles
     */
    public function setRequiredRoles($requiredRoles)
    {
        $this->requiredRoles = $requiredRoles;
    }

    /**
     * @return null|string
     */
    public function getRequiredChannel()
    {
        return $this->requiredChannel;
    }

    /**
     * @param null|string $requiredChannel
     */
    public function setRequiredChannel($requiredChannel)
    {
        $this->requiredChannel = $requiredChannel;
    }
}
