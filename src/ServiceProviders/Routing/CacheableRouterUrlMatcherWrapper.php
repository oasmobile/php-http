<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 20:47
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

class CacheableRouterUrlMatcherWrapper implements UrlMatcherInterface
{
    /** @var  UrlMatcherInterface */
    protected $other;
    /** @var  array */
    protected $namespaces;
    
    public function __construct(UrlMatcherInterface $other, array $namespaces)
    {
        $this->other      = $other;
        $this->namespaces = $namespaces;
    }
    
    /**
     * Sets the request context.
     *
     * @param RequestContext $context The context
     */
    public function setContext(RequestContext $context)
    {
        $this->other->setContext($context);
    }
    
    /**
     * Gets the request context.
     *
     * @return RequestContext The context
     */
    public function getContext()
    {
        return $this->other->getContext();
    }
    
    /**
     * Tries to match a URL path with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented
     * below.
     *
     * @param string $pathinfo The path info to be parsed (raw format, i.e. not urldecoded)
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     */
    public function match($pathinfo)
    {
        /** @var string[] $result */
        $result = $this->other->match($pathinfo);
        
        if (\is_string($result['_controller']) && strpos($result['_controller'], "::") !== false) {
            // check if we should prepend controller namespace
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($className, $methodName) = explode("::", $result['_controller'], 2);
            if (!class_exists($className)) {
                if ($this->namespaces) {
                    foreach ($this->namespaces as $namespace) {
                        $namespace = rtrim($namespace, "\\");
                        if (class_exists($namespace . "\\" . $className)) {
                            $result['_controller'] = $namespace . "\\" . $result['_controller'];
                            break;
                        }
                    }
                }
            }
        }
        
        return $result;
        
    }
}
