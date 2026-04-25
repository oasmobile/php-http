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
use Oasis\Mlib\Utils\DataType;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

class CacheableRouterProvider
{
    use ConfigurationValidationTrait;

    protected ?Router $router = null;
    protected ?MicroKernel $kernel = null;

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
    public function getConfigDataProvider(): DataProviderInterface
    {
        if (!$this->kernel) {
            throw new \LogicException("Cannot get config data provider before registration");
        }

        $dp = $this->kernel->getRoutingConfigDataProvider();
        if ($dp === null) {
            throw new \LogicException("Cannot get config data provider: routing not configured");
        }

        return $dp;
    }

    /**
     * @param RequestContext $requestContext
     *
     * @return Router
     */
    public function getRouter(RequestContext $requestContext): Router
    {
        if (!$this->router) {
            $configDp = $this->getConfigDataProvider();

            $routerFile = 'routes.yml';
            $routerPath = $configDp->getMandatory('path');
            if (!is_dir($routerPath)) {
                $routerFile = basename($routerPath);
                $routerPath = dirname($routerPath);
            }

            if ($this->kernel === null) {
                throw new \LogicException("Cannot use CacheableRouterProvider before registration");
            }

            $cacheDir                = strcasecmp($configDp->getOptional('cache_dir', DataType::String, ''), "false") === 0 ? null :
                ($configDp->getOptional('cache_dir') ?: $this->kernel->getCacheDir() . "/routing");
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
            DataType::Array,
            []
        );

        $matcher = $this->getRouter($requestContext)->getMatcher();
        if (!$matcher instanceof \Symfony\Component\Routing\Matcher\UrlMatcherInterface) {
            throw new \LogicException('Router matcher must implement UrlMatcherInterface');
        }

        return new GroupUrlMatcher(
            $requestContext,
            [
                new CacheableRouterUrlMatcherWrapper(
                    $matcher,
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
