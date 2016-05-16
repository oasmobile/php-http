<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 20:43
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Silex\Application;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;

class CacheableRouterUrlGeneratorProvider extends UrlGeneratorServiceProvider
{
    /** @var  CacheableRouterProvider */
    protected $routerProvider;

    public function __construct(CacheableRouterProvider $routerProvider)
    {
        $this->routerProvider = $routerProvider;
    }

    public function register(Application $app)
    {
        parent::register($app);

        $app['url_generator'] = $app->share(
            $app->extend(
                'url_generator',
                function ($generator, $c) {
                    /** @var RequestContext $context */
                    $context = $c['request_context'];

                    $router       = $this->routerProvider->getRouter($context);
                    $newGenerator = new UrlGenerator($router->getRouteCollection(), $context);

                    return new GroupUrlGenerator(
                        [
                            $newGenerator,
                            $generator,
                        ]
                    );
                }
            )
        );
    }
    
}
