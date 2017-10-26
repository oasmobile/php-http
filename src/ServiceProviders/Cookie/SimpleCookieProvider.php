<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-04-20
 * Time: 22:34
 */

namespace Oasis\Mlib\Http\ServiceProviders\Cookie;

use Oasis\Mlib\Http\SilexKernel;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SimpleCookieProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /** @var ResponseCookieContainer */
    protected $cookieContainer;
    
    public function __construct()
    {
        $this->cookieContainer = new ResponseCookieContainer();
    }
    
    /**
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $app
     */
    public function register(Container $app)
    {
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
        if (!$app instanceof SilexKernel) {
            throw new \LogicException(static::class . " can only be used with " . SilexKernel::class);
        }
        
        $app->addControllerInjectedArg($this->cookieContainer);
        $app->after(
            function (/** @noinspection PhpUnusedParameterInspection */
                Request $request,
                Response $response) {
                foreach ($this->cookieContainer->getCookies() as $cookie) {
                    $response->headers->setCookie($cookie);
                }
            }
        );
    }
}
