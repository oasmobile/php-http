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
use Oasis\Mlib\Utils\DataProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;

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
    
    /** @var RequestMatcher */
    protected $matcher            = null;
    protected $originsAllowed     = [];
    protected $headersAllowed     = [];
    protected $headersExposed     = [];
    protected $maxAge             = 0;
    protected $credentialsAllowed = false;
    
    /** @var  Request|null */
    protected $request;
    
    function __construct(array $configuration)
    {
        $dp = $this->processConfiguration($configuration, new CrossOriginResourceSharingConfiguration());
        
        $pattern                  = $dp->getMandatory('pattern', DataProviderInterface::MIXED_TYPE);
        $this->originsAllowed     = $dp->getMandatory('origins', DataProviderInterface::ARRAY_TYPE);
        $this->headersAllowed     = $dp->getOptional('headers', DataProviderInterface::ARRAY_TYPE, []);
        $this->headersExposed     = $dp->getOptional('headers_exposed', DataProviderInterface::ARRAY_TYPE, []);
        $this->maxAge             = $dp->getOptional('max_age', DataProviderInterface::INT_TYPE, 86400);
        $this->credentialsAllowed = $dp->getOptional('credentials_allowed', DataProviderInterface::BOOL_TYPE, false);
        
        if (is_string($pattern)) {
            if ($pattern == "*") {
                $this->matcher = new RequestMatcher('.*');
            }
            else {
                $this->matcher = new RequestMatcher($pattern);
            }
        }
        elseif ($pattern instanceof RequestMatcher) {
            $this->matcher = $pattern;
        }
        else {
            throw new \InvalidArgumentException(
                "Unrecognized type of pattern for CORS strategy. type = " . get_class($pattern)
            );
        }
    }
    
    public function matches(Request $request)
    {
        if ($this->matcher->matches($request)) {
            $this->request = $request;
            
            return true;
        }
        else {
            $this->request = null;
            
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
