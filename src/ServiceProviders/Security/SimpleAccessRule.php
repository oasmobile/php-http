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
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

class SimpleAccessRule implements AccessRuleInterface
{
    use ConfigurationValidationTrait;

    protected string|RequestMatcherInterface $pattern;
    protected array $requiredRoles;
    protected ?string $requiredChannel;

    public function __construct(array $ruleConfiguration)
    {
        $dp = $this->processConfiguration($ruleConfiguration, new SimpleAccessRuleConfiguration());

        $this->pattern         = $dp->getMandatory('pattern', DataProviderInterface::MIXED_TYPE);
        $this->requiredRoles   = $dp->getMandatory('roles', DataProviderInterface::ARRAY_TYPE);
        $this->requiredChannel = $dp->getOptional('channel', DataProviderInterface::STRING_TYPE);
    }

    /**
     * @return string|RequestMatcherInterface
     */
    public function getPattern(): string|RequestMatcherInterface
    {
        return $this->pattern;
    }

    /**
     * @param string|RequestMatcherInterface $pattern
     */
    public function setPattern(string|RequestMatcherInterface $pattern): void
    {
        $this->pattern = $pattern;
    }

    /**
     * @return array
     */
    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }

    /**
     * @param array $requiredRoles
     */
    public function setRequiredRoles(array $requiredRoles): void
    {
        $this->requiredRoles = $requiredRoles;
    }

    /**
     * @return null|string
     */
    public function getRequiredChannel(): ?string
    {
        return $this->requiredChannel;
    }

    /**
     * @param null|string $requiredChannel
     */
    public function setRequiredChannel(?string $requiredChannel): void
    {
        $this->requiredChannel = $requiredChannel;
    }
}
