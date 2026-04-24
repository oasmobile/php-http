<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 20:53
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Oasis\Mlib\Http\Configuration\CacheableRouterConfiguration;
use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Utils\DataProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

class CacheableRouterProvider
{
    use ConfigurationValidationTrait;

    /** @var Router */
    protected $router;
    /** @var MicroKernel */
    protected $kernel;

    public function __construct()
    {
    }

    /**
     * Register routing services on the given MicroKernel.
     *
     * Replaces the old Pimple ServiceProviderInterface::register(Container $app).
     * After calling this method, the kernel exposes routing services via getters:
     *   - getRequestMatcher()
     *   - getUrlGenerator()
     *   - getRouter()
     */
    public function register(MicroKernel $kernel): void
    {
        $this->kernel = $kernel;
    }

    /** @return DataProviderInterface */
    public function getConfigDataProvider()
    {
        if (!$this->kernel) {
            throw new \LogicException("Cannot get config data provider before registration");
        }

        return $this->kernel->getRoutingConfigDataProvider();
    }

    /**
     * @param RequestContext $requestContext
     *
     * @return Router
     */
    public function getRouter(RequestContext $requestContext)
    {
        if (!$this->router) {
            if (!$this->getConfigDataProvider()) {
                throw new \LogicException(
                    "Cannot use CacheableRouterProvider because 'routing.config' not configured."
                );
            }

            $routerFile = 'routes.yml';
            $routerPath = $this->getConfigDataProvider()->getMandatory('path');
            if (!is_dir($routerPath)) {
                $routerFile = basename($routerPath);
                $routerPath = dirname($routerPath);
            }

            $cacheDir                = strcasecmp($this->getConfigDataProvider()->getOptional('cache_dir', DataProviderInterface::STRING_TYPE, ''), "false") == 0 ? null :
                ($this->getConfigDataProvider()->getOptional('cache_dir') ?: $this->kernel->getCacheDir() . "/routing");
            $locator                 = new FileLocator([$routerPath]);
            $this->router            = new CacheableRouter(
                $this->kernel,
                new InheritableYamlFileLoader($locator),
                $routerFile,
                [
                    'cache_dir' => $cacheDir,
                    "debug"     => $this->kernel->isDebug(),
                ],
                $requestContext
            );
        }

        return $this->router;
    }

    /**
     * Build the request matcher (GroupUrlMatcher) for this routing provider.
     *
     * @param RequestContext $requestContext
     *
     * @return GroupUrlMatcher
     */
    public function buildRequestMatcher(RequestContext $requestContext): GroupUrlMatcher
    {
        $namespaces = $this->getConfigDataProvider()->getOptional(
            'namespaces',
            DataProviderInterface::ARRAY_TYPE,
            []
        );

        return new GroupUrlMatcher(
            $requestContext,
            [
                new CacheableRouterUrlMatcherWrapper(
                    $this->getRouter($requestContext)->getMatcher(),
                    $namespaces
                ),
            ]
        );
    }

    /**
     * Build the URL generator (GroupUrlGenerator) for this routing provider.
     *
     * @param RequestContext $requestContext
     *
     * @return GroupUrlGenerator
     */
    public function buildUrlGenerator(RequestContext $requestContext): GroupUrlGenerator
    {
        $router       = $this->getRouter($requestContext);
        $newGenerator = $router->getGenerator();

        return new GroupUrlGenerator(
            [
                $newGenerator,
            ]
        );
    }
}
