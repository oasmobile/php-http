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
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Utils\DataProviderInterface;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

class CacheableRouterProvider implements ServiceProviderInterface
{
    use ConfigurationValidationTrait;
    
    /** @var Router */
    protected $router;
    /** @var  SilexKernel */
    protected $kernel;
    
    public function __construct()
    {
    }
    
    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Application $app
     */
    public function register(Application $app)
    {
        $this->kernel                        = $app;
        $app['url_matcher']                  = $app->share(
            $app->extend(
                'url_matcher',
                function ($urlMatcher, $c) {
                    $context = $c['request_context'];
                    
                    $newMatcher = new GroupUrlMatcher(
                        $context,
                        [
                            new CacheableRouterUrlMatcherWrapper(
                                $this->getRouter($context)->getMatcher(),
                                $c['routing.config.namespaces']
                            ),
                            $urlMatcher,
                        ]
                    );
                    
                    return $newMatcher;
                }
            )
        );
        $app['router']                       = $app->share(
            function ($app) {
                return $this->getRouter($app['request_context']);
            }
        );
        $app['routing.config.data_provider'] = $app->share(
            function ($app) {
                $routingConfig = $app['routing.config'];
                
                return $this->processConfiguration(
                    $routingConfig,
                    new CacheableRouterConfiguration()
                );
            }
        );
        $app['routing.config.namespaces']    = $app->share(
            function () {
                return $this->getConfigDataProvider()->getOptional(
                    'namespaces',
                    DataProviderInterface::ARRAY_TYPE,
                    []
                );
            }
        );
        $app['routing.config.cache_dir']     = $app->share(
            function () {
                return $this->getConfigDataProvider()->getOptional('cache_dir');
            }
        );
    }
    
    /**
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {
    }
    
    /** @return DataProviderInterface */
    public function getConfigDataProvider()
    {
        if (!$this->kernel) {
            throw new \LogicException("Cannot get config data provider before registration");
        }
        
        return $this->kernel['routing.config.data_provider'];
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
            
            $cacheDir              = strcasecmp($this->kernel['routing.config.cache_dir'], "false") == 0 ? null :
                ($this->kernel['routing.config.cache_dir'] ? : $routerPath . "/cache");
            $matcherCacheClassname = "ProjectUrlMatcher_" . md5(
                    realpath($cacheDir) . "/" . realpath($routerPath) . "/" . $routerFile
                );
            $locator               = new FileLocator([$routerPath]);
            $this->router          = new CacheableRouter(
                $this->kernel,
                //new YamlFileLoader($locator),
                new InheritableYamlFileLoader($locator),
                $routerFile,
                [
                    'cache_dir'           => $cacheDir,
                    'matcher_cache_class' => $matcherCacheClassname,
                    "debug"               => $this->kernel['debug'],
                ],
                $requestContext
            );
        }
        
        return $this->router;
    }
}
