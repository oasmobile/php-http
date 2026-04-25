<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-07-11
 * Time: 18:30
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Oasis\Mlib\Http\MicroKernel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;

class CacheableRouter extends Router
{
    private MicroKernel $kernel;
    private bool $isParamReplaced = false;
    
    /**
     * CacheableRouter constructor.
     *
     * @param MicroKernel          $kernel
     * @param LoaderInterface      $loader
     * @param mixed                $resource
     * @param array                $options
     * @param RequestContext|null  $context
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        MicroKernel $kernel,
        LoaderInterface $loader,
        mixed $resource,
        array $options = [],
        ?RequestContext $context = null,
        ?LoggerInterface $logger = null)
    {
        parent::__construct($loader, $resource, $options, $context, $logger);
        $this->kernel = $kernel;
    }
    
    public function getRouteCollection(): \Symfony\Component\Routing\RouteCollection
    {
        $collection = parent::getRouteCollection();
        if (!$this->isParamReplaced) {
            /** @var Route $route */
            foreach ($collection as $route) {
                $defaults = $route->getDefaults();
                foreach ($defaults as $name => $value) {
                    if (!is_string($value)) {
                        continue;
                    }
                    $offset = 0;
                    while (preg_match('#(%([^%].*?)%)#', $value, $matches, 0, $offset)) {
                        $key         = $matches[2];
                        $replacement = $this->kernel->getParameter($key);
                        if ($replacement === null) {
                            $offset += strlen($key) + 2;
                            continue;
                        }
                        $value = preg_replace("/" . preg_quote($matches[1], '/') . "/", $replacement, $value, 1);
                    }
                    $value = preg_replace('#%%#', '%', $value);
                    $route->setDefault($name, $value);
                }
            }
            $collection->addResource(new FileResource(__FILE__));
            $this->isParamReplaced = true;
        }
        
        return $collection;
    }
    
}
