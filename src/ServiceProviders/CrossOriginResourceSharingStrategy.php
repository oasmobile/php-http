<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 20:20
 */

namespace Oasis\Mlib\Http\ServiceProviders;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\CrossOriginResourceSharingConfiguration;
use Oasis\Mlib\Utils\DataProviderInterface;
use Oasis\Mlib\Utils\StringUtils;

class CrossOriginResourceSharingStrategy
{
    const DOMAIN_MATCHING_PATTERN = "#^(https?://)?((((\\d+\\.){3}\\d+)|localhost|([a-z0-9\\.-]+)+\\.[a-z]+)(:\\d+)?)(/.*)?\$#";

    const SIMPLE_REQUEST_HEADERS = [
        "accept",
        "accept-language",
        "content-language",
        "content-type",
        "origin",
    ];

    use ConfigurationValidationTrait;

    protected $path                   = "/";
    protected $pathEndsWithWildcard   = false;
    protected $pathStartsWithWildcard = false;
    protected $pathPattern            = "#^.*\$#";
    protected $originsAllowed         = [];
    protected $headersAllowed         = [];
    protected $headersExposed         = [];
    protected $maxAge                 = 0;
    protected $credentialsAllowed     = false;

    function __construct(array $configuration)
    {
        $dp = $this->processConfiguration($configuration, new CrossOriginResourceSharingConfiguration());

        $this->path               = $dp->getMandatory('path');
        $this->originsAllowed     = $dp->getMandatory('origins', DataProviderInterface::ARRAY_TYPE);
        $this->headersAllowed     = $dp->getOptional('headers', DataProviderInterface::ARRAY_TYPE, []);
        $this->headersExposed     = $dp->getOptional('headers_exposed', DataProviderInterface::ARRAY_TYPE, []);
        $this->maxAge             = $dp->getOptional('max_age', DataProviderInterface::INT_TYPE, 86400);
        $this->credentialsAllowed = $dp->getOptional('credentials_allowed', DataProviderInterface::BOOL_TYPE, false);

        $this->pathPattern = '#^' . addcslashes($this->path, '\\#') . '$#';
        if (StringUtils::stringStartsWith($this->path, "*")) {
            $this->pathStartsWithWildcard = true;
        }
        if (StringUtils::stringEndsWith($this->path, "*")) {
            $this->pathEndsWithWildcard = true;
        }
        $this->path = trim($this->path, '*');
    }

    public function match($path)
    {
        if ($path === $this->path) {
            return true;
        }

        if (@preg_match($this->pathPattern, $path)) {
            return true;
        }

        if ($this->pathStartsWithWildcard && $this->pathEndsWithWildcard) {
            return $this->path === '' || strpos($path, $this->path) !== false;
        }
        elseif ($this->pathStartsWithWildcard) {
            return StringUtils::stringEndsWith($path, $this->path);
        }
        elseif ($this->pathEndsWithWildcard) {
            return StringUtils::stringStartsWith($path, $this->path);
        }
        else {
            return false;
        }
    }

    public function isOriginAllowed($origin)
    {
        if (!preg_match(self::DOMAIN_MATCHING_PATTERN, $origin, $matches)) {
            return false;
        }
        $origin = $matches[2];

        if (sizeof($this->originsAllowed)
            && !in_array($origin, $this->originsAllowed)
            && !$this->isWildcardOriginAllowed()
        ) {
            return false;
        }
        else {
            return true;
        }
    }

    public function isWildcardOriginAllowed()
    {
        return in_array("*", $this->originsAllowed);
    }

    public function isHeaderAllowed($header)
    {
        $header = strtolower($header);

        if (!in_array($header, static::SIMPLE_REQUEST_HEADERS)
            && !in_array($header, array_map('strtolower', $this->headersAllowed))
        ) {
            mdebug("Header %s is not in allowed header list", $header);

            return false;
        }
        else {
            return true;
        }
    }

    /**
     * @return bool
     */
    public function isCredentialsAllowed()
    {
        return $this->credentialsAllowed;
    }

    /**
     * @return int|mixed
     */
    public function getMaxAge()
    {
        return $this->maxAge;
    }

    public function getAllowedHeaders()
    {
        return implode(", ", $this->headersAllowed);
    }

    public function getExposedHeaders()
    {
        return implode(", ", $this->headersExposed);
    }
}
