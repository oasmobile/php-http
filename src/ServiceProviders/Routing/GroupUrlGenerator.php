<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 21:07
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class GroupUrlGenerator implements UrlGeneratorInterface
{
    /** @var  UrlGeneratorInterface[] */
    protected $generators;
    
    /** @var  RequestContext */
    protected $context;
    
    /**
     * GroupUrlGenerator constructor.
     *
     * @param UrlGeneratorInterface[] $generators
     */
    public function __construct(array $generators)
    {
        $this->generators = $generators;
    }
    
    /**
     * Sets the request context.
     *
     * @param RequestContext $context The context
     */
    public function setContext(RequestContext $context)
    {
        $this->context = $context;
    }
    
    /**
     * Gets the request context.
     *
     * @return RequestContext The context
     */
    public function getContext()
    {
        return $this->context;
    }
    
    /**
     * Generates a URL or path for a specific route based on the given parameters.
     *
     * Parameters that reference placeholders in the route pattern will substitute them in the
     * path or host. Extra params are added as query string to the URL.
     *
     * When the passed reference type cannot be generated for the route because it requires a different
     * host or scheme than the current one, the method will return a more comprehensive reference
     * that includes the required params. For example, when you call this method with $referenceType = ABSOLUTE_PATH
     * but the route requires the https scheme whereas the current scheme is http, it will instead return an
     * ABSOLUTE_URL with the https scheme and the current host. This makes sure the generated URL matches
     * the route in any case.
     *
     * If there is no route with the given name, the generator must throw the RouteNotFoundException.
     *
     * @param string $name          The name of the route
     * @param mixed  $parameters    An array of parameters
     * @param int    $referenceType The type of reference to be generated (one of the constants)
     *
     * @return string The generated URL
     *
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     */
    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH)
    {
        $total = sizeof($this->generators);
        $found = 0;
        
        foreach ($this->generators as $generator) {
            $found++;
            try {
                if ($this->getContext()) {
                    $generator->setContext($this->getContext());
                }
                
                return $generator->generate($name, $parameters, $referenceType);
            } catch (RouteNotFoundException $e) {
                if ($found == $total) {
                    // already last url generator
                    throw $e;
                }
            }
        }
        
        throw new RouteNotFoundException("Cannot find route named '$name'");
    }
}
