<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 20:43
 */

namespace Oasis\Mlib\Http\ServiceProviders\Routing;

use Oasis\Mlib\Http\SilexKernel;
use Silex\Application;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

class CacheableRouterUrlGeneratorProvider extends UrlGeneratorServiceProvider
{
    public function __construct()
    {
    }
    
    public function register(Application $app)
    {
        parent::register($app);
        
        $app['url_generator'] = $app->share(
            $app->extend(
                'url_generator',
                function ($generator, $kernel) {
                    /** @var SilexKernel $kernel */
                    
                    /** @var RequestContext $context */
                    //$context = $kernel['request_context'];
                    /** @var Router $router */
                    $router       = $kernel['router'];
                    $newGenerator = $router->getGenerator();
                    
                    //$newGenerator = new UrlGenerator($router->getRouteCollection(), $context);
                    
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
