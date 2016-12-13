<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 20:16
 */

namespace Oasis\Mlib\Http\ServiceProviders\Cors;

use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Views\PrefilightResponse;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class CrossOriginResourceSharingProvider implements ServiceProviderInterface
{
    const HEADER_REQUEST_ORIGIN  = "Origin";
    const HEADER_REQUEST_METHOD  = "Access-Control-Request-Method";
    const HEADER_REQUEST_HEADERS = "Access-Control-Request-Headers";
    
    const HEADER_ALLOW_ORIGIN      = "Access-Control-Allow-Origin";
    const HEADER_VARY              = "Vary";
    const HEADER_ALLOW_METHODS     = "Access-Control-Allow-Methods";
    const HEADER_ALLOW_HEADERS     = "Access-Control-Allow-Headers";
    const HEADER_EXPOSE_HEADERS    = "Access-Control-Expose-Headers";
    const HEADER_ALLOW_CREDENTIALS = "Access-Control-Allow-Credentials";
    const HEADER_MAX_AGE           = "Access-Control-Max-Age";
    
    const SIMPLE_METHODS = [
        'HEAD',
        'POST',
        'GET',
    ];
    
    /** @var  CrossOriginResourceSharingStrategy[] */
    protected $strategies = [];
    /** @var  CrossOriginResourceSharingStrategy */
    protected $activeStrategy = null;
    /** @var PrefilightResponse|null */
    protected $preFlightResponse = null;
    
    /**
     * CrossOriginResourceSharingProvider constructor.
     */
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
        $this->strategies = $app['cors.strategies'];
        foreach ($this->strategies as &$strategy) {
            if (is_array($strategy)) {
                $strategy = new CrossOriginResourceSharingStrategy($strategy);
            }
            elseif (!$strategy instanceof CrossOriginResourceSharingStrategy) {
                throw new \InvalidArgumentException(
                    static::class . " must be constructed with array of " . CrossOriginResourceSharingStrategy::class
                );
            }
        }
        
        $app->before([$this, 'onPreRouting'], SilexKernel::BEFORE_PRIORITY_ROUTING + 1);
        $app->before([$this, 'onPostRouting'], SilexKernel::BEFORE_PRIORITY_CORS_PREFLIGHT);
        $app->after([$this, 'onResponse'], Application::LATE_EVENT);
        //$app->error([$this, 'onMethodNotAllowed'], Application::EARLY_EVENT);
        $app->error([$this, 'onMethodNotAllowedHttp'], Application::EARLY_EVENT);
    }
    
    /**
     *
     * Finds out active CORS strategy for the request.
     *
     * Also decides if the request is a Pre-Flight request
     *
     * @param Request $request
     */
    public function onPreRouting(Request $request)
    {
        $this->activeStrategy    = null;
        $this->preFlightResponse = null;
        
        if (!$request->headers->has(static::HEADER_REQUEST_ORIGIN)) {
            return;
        }
        
        foreach ($this->strategies as $strategy) {
            if ($strategy->matches($request)) {
                $this->activeStrategy = $strategy;
                break;
            }
        }
        
        if (!$this->activeStrategy) {
            return;
        }
        
        if ($request->getMethod() === "OPTIONS"
            && $request->headers->has(static::HEADER_REQUEST_METHOD)
        ) {
            $this->preFlightResponse = new PrefilightResponse();
        }
    }
    
    public function onPostRouting(Request $request)
    {
        if ($this->preFlightResponse) {
            $this->preFlightResponse->addAllowedMethod($request->headers->get(static::HEADER_REQUEST_METHOD));
            
            return $this->preFlightResponse;
        }
        
        return null;
    }
    
    //public function onMethodNotAllowed(MethodNotAllowedException $e)
    //{
    //    if ($this->activeStrategy && $this->isPreflight) {
    //        return new PrefilightResponse($e->getAllowedMethods());
    //    }
    //    else {
    //        return null;
    //    }
    //}
    
    public function onMethodNotAllowedHttp(MethodNotAllowedHttpException $e)
    {
        if ($this->preFlightResponse) {
            foreach (explode(', ', $e->getHeaders()['Allow']) as $method) {
                $this->preFlightResponse->addAllowedMethod($method);
            }
            
            return $this->preFlightResponse;
        }
        else {
            return null;
        }
    }
    
    public function onResponse(Request $request, Response $response)
    {
        if ($this->activeStrategy) {
            // This function will process according to spec https://www.w3.org/TR/cors/#resource-processing-model
            
            if ($response instanceof PrefilightResponse) {
                // PREFLIGHT REQUEST STEPS:
                
                // 1. skip setting access control headersAllowed if no 'origin' header is provided in request
                if (!$request->headers->has(static::HEADER_REQUEST_ORIGIN)) {
                    return;
                }
                
                // 2. skip if origin is not allowed
                $requestOrigin = $request->headers->get(static::HEADER_REQUEST_ORIGIN);
                if (!$this->activeStrategy->isOriginAllowed($requestOrigin)) {
                    return;
                }
                
                // 3. terminate if no request-method header is set
                if (!$request->headers->has(static::HEADER_REQUEST_METHOD)) {
                    return;
                }
                $requestMethod = strtoupper($request->headers->get(static::HEADER_REQUEST_METHOD));
                
                // 4. prepare request headers
                if ($request->headers->has(static::HEADER_REQUEST_HEADERS)) {
                    $requestHeaders = explode(",", $request->headers->get(static::HEADER_REQUEST_HEADERS));
                }
                else {
                    $requestHeaders = [];
                }
                
                // 5. terminate if method is not allowed
                if (empty($methodsAllowed = $response->getAllowedMethods())) {
                    return;
                }
                if (!in_array($requestMethod, $methodsAllowed)) {
                    return;
                }
                
                // 6. terminate if header is not allowed
                foreach ($requestHeaders as $header) {
                    $header = trim($header);
                    if (!$this->activeStrategy->isHeaderAllowed($header)) {
                        return;
                    }
                }
                
                // 7. set allow-origin header
                if ($this->activeStrategy->isCredentialsAllowed()) {
                    $response->headers->add([static::HEADER_ALLOW_CREDENTIALS => 'true']);
                    $response->headers->add([static::HEADER_ALLOW_ORIGIN => $requestOrigin]);
                }
                else {
                    if ($this->activeStrategy->isWildcardOriginAllowed()) {
                        $response->headers->add([static::HEADER_ALLOW_ORIGIN => "*"]);
                        
                    }
                    else {
                        $response->headers->add([static::HEADER_ALLOW_ORIGIN => $requestOrigin]);
                        $response->headers->add([static::HEADER_VARY => 'Origin']);
                    }
                }
                
                // 8. set max age
                $response->headers->add([static::HEADER_MAX_AGE => $this->activeStrategy->getMaxAge()]);
                
                // 9. set allow methods if method is not simple method
                if (!in_array($requestMethod, static::SIMPLE_METHODS)) {
                    $response->headers->add(
                        [static::HEADER_ALLOW_METHODS => strtoupper(implode(', ', $methodsAllowed))]
                    );
                }
                
                // 10. set allow headers
                if ($headersAllwed = $this->activeStrategy->getAllowedHeaders()) {
                    $response->headers->add([static::HEADER_ALLOW_HEADERS => $headersAllwed]);
                }
            }
            else {
                // NORMAL REQUEST STEPS:
                
                // 1. skip setting access control headersAllowed if no 'origin' header is provided in request
                if (!$request->headers->has(static::HEADER_REQUEST_ORIGIN)) {
                    return;
                }
                
                // 2. skip if origin is not allowed
                $requestOrigin = $request->headers->get(static::HEADER_REQUEST_ORIGIN);
                if (!$this->activeStrategy->isOriginAllowed($requestOrigin)) {
                    return;
                }
                
                // 3. set allow-origin header
                if ($this->activeStrategy->isCredentialsAllowed()) {
                    $response->headers->add([static::HEADER_ALLOW_CREDENTIALS => 'true']);
                    $response->headers->add([static::HEADER_ALLOW_ORIGIN => $requestOrigin]);
                }
                else {
                    if ($this->activeStrategy->isWildcardOriginAllowed()) {
                        $response->headers->add([static::HEADER_ALLOW_ORIGIN => "*"]);
                        
                    }
                    else {
                        $response->headers->add([static::HEADER_ALLOW_ORIGIN => $requestOrigin]);
                        $response->headers->add([static::HEADER_VARY => 'Origin']);
                    }
                }
                
                // 4. list exposed headers
                if ($headersExposed = $this->activeStrategy->getExposedHeaders()) {
                    $response->headers->add([static::HEADER_EXPOSE_HEADERS => $headersExposed]);
                }
            }
        }
    }
}
