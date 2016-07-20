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
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;

class CacheableRouterProvider implements ServiceProviderInterface
{
    use ConfigurationValidationTrait;

    /** @var Router */
    protected $router;
    /** @var  DataProviderInterface */
    protected $configDataProvider;
    protected $cacheDir             = null;
    protected $isDebug              = false;
    protected $controllerNamespaces = [];

    /** @var  SilexKernel */
    protected $kernel;

    public function __construct(array $configuration, $isDebug)
    {
        $this->configDataProvider = $this->processConfiguration($configuration, new CacheableRouterConfiguration());

        $this->controllerNamespaces = $this->configDataProvider->getOptional(
            'namespaces',
            DataProviderInterface::ARRAY_TYPE,
            []
        );
        $this->cacheDir             = $this->configDataProvider->getOptional('cache_dir');
        $this->isDebug              = $isDebug;
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
        $app['url_matcher'] = $app->share(
            $app->extend(
                'url_matcher',
                function ($urlMatcher, $c) {
                    $context = $c['request_context'];

                    $newMatcher = new GroupUrlMatcher(
                        $context,
                        [
                            new CacheableRouterUrlMatcherWrapper(
                                $this->getRouter($context)->getMatcher(),
                                $this->controllerNamespaces
                            ),
                            $urlMatcher,
                        ]
                    );

                    return $newMatcher;
                }
            )
        );

        $this->kernel = $app;
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

    public function getRouter(RequestContext $requestContext)
    {
        if (!$this->router) {
            $routerFile = 'routes.yml';
            $routerPath = $this->configDataProvider->getMandatory('path');
            if (!is_dir($routerPath)) {
                $routerFile = basename($routerPath);
                $routerPath = dirname($routerPath);
            }

            $cacheDir              = strcasecmp($this->cacheDir, "false") == 0 ? null :
                ($this->cacheDir ? : $routerPath . "/cache");
            $matcherCacheClassname = "ProjectUrlMatcher_" . md5(realpath($cacheDir));
            $locator               = new FileLocator([$routerPath]);
            $this->router          = new Router(
            //new YamlFileLoader($locator),
                new InheritableYamlFileLoader($locator),
                $routerFile,
                [
                    'cache_dir'           => $cacheDir,
                    'matcher_cache_class' => $matcherCacheClassname,
                    "debug"               => $this->isDebug,
                ],
                $requestContext
            );
            $collection            = $this->router->getRouteCollection();
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
                            $offset += strlen($key + 2);
                            continue;
                        }
                        $value = preg_replace("/" . preg_quote($matches[1], '/') . "/", $replacement, $value, 1);
                    }
                    $value = preg_replace('#%%#', '%', $value);
                    $route->setDefault($name, $value);
                }
            }
            $collection->addResource(new FileResource(__FILE__));
        }

        return $this->router;
    }
}
