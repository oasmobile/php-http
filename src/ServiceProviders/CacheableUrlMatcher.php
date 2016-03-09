<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 21:06
 */

namespace Oasis\Mlib\Http\ServiceProviders;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

class CacheableUrlMatcher implements UrlMatcherInterface
{
    /** @var  RequestContext */
    protected $context;
    /** @var  UrlMatcherInterface */
    protected $primaryMatcher;
    /** @var  UrlMatcherInterface */
    protected $silexMatcher;
    /** @var  array */
    protected $namespaces;

    public function __construct(RequestContext $context,
                                UrlMatcherInterface $primaryMatcher,
                                UrlMatcherInterface $silexMatcher,
                                array $namespaces
    )
    {
        $this->context        = $context;
        $this->primaryMatcher = $primaryMatcher;
        $this->silexMatcher   = $silexMatcher;
        $this->namespaces     = $namespaces;
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
        try {
            $result = $this->primaryMatcher->match($pathinfo);

            // check if we should prepend controller namespace
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($className, $methodName) = explode("::", $result['_controller'], 2);
            if (!class_exists($className)) {
                if ($this->namespaces) {
                    foreach ($this->namespaces as $namespace) {
                        if (class_exists($namespace . "\\" . $className)) {
                            $result['_controller'] = $namespace . "\\" . $result['_controller'];
                            break;
                        }
                    }
                }
            }
        } catch (ResourceNotFoundException $e) {
            $result = $this->silexMatcher->match($pathinfo);
        }

        return $result;
    }
}
