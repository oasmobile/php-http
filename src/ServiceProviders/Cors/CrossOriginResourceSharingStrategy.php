<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 20:20
 */

namespace Oasis\Mlib\Http\ServiceProviders\Cors;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\CrossOriginResourceSharingConfiguration;
use Oasis\Mlib\Utils\DataType;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\HostRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

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
    
    protected ?RequestMatcherInterface $matcher = null;
    /** @var array<string> */
    protected array $originsAllowed            = [];
    /** @var array<string> */
    protected array $headersAllowed            = [];
    /** @var array<string> */
    protected array $headersExposed            = [];
    protected int $maxAge                      = 0;
    protected bool $credentialsAllowed         = false;
    protected ?Request $request                = null;
    
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(array $configuration)
    {
        $dp = $this->processConfiguration($configuration, new CrossOriginResourceSharingConfiguration());
        
        $pattern                  = $dp->getMandatory('pattern', DataType::Mixed);
        $this->originsAllowed     = $dp->getMandatory('origins', DataType::Array);
        $this->headersAllowed     = $dp->getOptional('headers', DataType::Array, []);
        $this->headersExposed     = $dp->getOptional('headers_exposed', DataType::Array, []);
        $this->maxAge             = $dp->getOptional('max_age', DataType::Int, 86400);
        $this->credentialsAllowed = $dp->getOptional('credentials_allowed', DataType::Bool, false);
        
        if (is_string($pattern)) {
            if ($pattern === "*") {
                $this->matcher = new ChainRequestMatcher([new PathRequestMatcher('.*')]);
            }
            else {
                $this->matcher = new ChainRequestMatcher([new PathRequestMatcher($pattern)]);
            }
        }
        elseif ($pattern instanceof RequestMatcherInterface) {
            $this->matcher = $pattern;
        }
        else {
            throw new \InvalidArgumentException(
                "Unrecognized type of pattern for CORS strategy. type = " . get_class($pattern)
            );
        }
    }
    
    public function matches(Request $request): bool
    {
        if ($this->matcher !== null && $this->matcher->matches($request)) {
            $this->request = $request;
            
            return true;
        }
        else {
            $this->request = null;
            
            return false;
        }
    }
    
    public function isOriginAllowed(string $origin): bool
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
    
    public function isWildcardOriginAllowed(): bool
    {
        return in_array("*", $this->originsAllowed);
    }
    
    public function isHeaderAllowed(string $header): bool
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
    
    public function isCredentialsAllowed(): bool
    {
        return $this->credentialsAllowed;
    }
    
    public function getMaxAge(): int
    {
        return $this->maxAge;
    }
    
    public function getAllowedHeaders(): string
    {
        return implode(", ", $this->headersAllowed);
    }
    
    public function getExposedHeaders(): string
    {
        return implode(", ", $this->headersExposed);
    }
}
