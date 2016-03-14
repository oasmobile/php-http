<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 21:06
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

class GroupUrlMatcher implements UrlMatcherInterface
{
    /** @var  RequestContext */
    protected $context;
    /** @var  UrlMatcherInterface[] */
    protected $matchers;

    /**
     * GroupUrlMatcher constructor.
     *
     * @param RequestContext        $context
     * @param UrlMatcherInterface[] $matchers
     */
    public function __construct(RequestContext $context,
                                array $matchers
    )
    {
        $this->context  = $context;
        $this->matchers = $matchers;
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
        $total   = sizeof($this->matchers);
        $matched = 0;
        foreach ($this->matchers as $matcher) {
            $matched++;
            try {
                $result = $matcher->match($pathinfo);

                // matched
                return $result;
            } catch (ResourceNotFoundException $e) {
                if ($matched == $total) {
                    // already last matcher
                    throw $e;
                }
            }
        }

        throw new ResourceNotFoundException("Cannot find route for $pathinfo");
    }
}
