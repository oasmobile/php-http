<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 20:53
 */

namespace Oasis\Mlib\Http\ServiceProviders;

use Oasis\Mlib\Http\Configuration\CacheableRouterConfiguration;
use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Utils\DataProviderInterface;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

class CacheableRouterProvider implements ServiceProviderInterface
{
    use ConfigurationValidationTrait;

    /** @var Router */
    protected $router;
    /** @var  DataProviderInterface */
    protected $configDataProvider;
    protected $isDebug              = false;
    protected $controllerNamespaces = [];

    public function __construct(array $configuration, $isDebug)
    {
        $this->configDataProvider = $this->processConfiguration($configuration, new CacheableRouterConfiguration());

        $this->controllerNamespaces = $this->configDataProvider->getOptional(
            'namespaces',
            DataProviderInterface::ARRAY_TYPE,
            []
        );
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
        $app->extend(
            'url_matcher',
            function ($urlMatcher, $c) {
                $context = $c['request_context'];

                $newMatcher = new CacheableUrlMatcher(
                    $context,
                    $this->getRouter($context)->getMatcher(),
                    $urlMatcher,
                    $this->controllerNamespaces
                );

                return $newMatcher;
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
        //$app->before([$this, 'route'], Application::EARLY_EVENT);
    }

    //public function route(Request $request)
    //{
    //    try {
    //        $router     = $this->getRouter($request);
    //        $attributes = $this->router->match($request->getPathInfo());
    //
    //        // check if we should prepend controller namespace
    //        /** @noinspection PhpUnusedLocalVariableInspection */
    //        list($className, $methodName) = explode("::", $attributes['_controller'], 2);
    //        if (!class_exists($className)) {
    //            if ($this->controllerNamespaces) {
    //                foreach ($this->controllerNamespaces as $namespace) {
    //                    if (class_exists($namespace . "\\" . $className)) {
    //                        $attributes['_controller'] = $namespace . "\\" . $attributes['_controller'];
    //                        break;
    //                    }
    //                }
    //            }
    //        }
    //
    //        // add resolved routing info to request object
    //        foreach ($attributes as $k => $v) {
    //            $request->attributes->set($k, $v);
    //        }
    //        unset($attributes['_controller']);
    //        unset($attributes['_route']);
    //        $request->attributes->set('_route_params', $attributes);
    //
    //    } catch (ResourceNotFoundException $e) {
    //        // routing will be further handled by Silex
    //        mdebug("Path %s not found in router", $request->getPathInfo());
    //    }
    //}

    protected function getRouter(RequestContext $requestContext)
    {
        if (!$this->router) {
            $routerFile = 'routes.yml';
            $routerPath = $this->configDataProvider->getMandatory('path');
            if (!is_dir($routerPath)) {
                $routerFile = basename($routerPath);
                $routerPath = dirname($routerPath);
            }

            $locator      = new FileLocator([$routerPath]);
            $this->router = new Router(
                new YamlFileLoader($locator),
                $routerFile,
                [
                    'cache_dir' => $routerPath . '/cache',
                    "debug"     => $this->isDebug,
                ],
                $requestContext
            );
            $this->router->getRouteCollection()->addResource(new FileResource(__FILE__));
        }

        return $this->router;
    }
}
