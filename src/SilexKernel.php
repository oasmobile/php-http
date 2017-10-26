<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-01
 * Time: 10:30
 */

namespace Oasis\Mlib\Http;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\HttpConfiguration;
use Oasis\Mlib\Http\Middlewares\MiddlewareInterface;
use Oasis\Mlib\Http\ServiceProviders\Cookie\SimpleCookieProvider;
use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterProvider;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\ServiceProviders\Twig\SimpleTwigServiceProvider;
use Oasis\Mlib\Logging\MLogging;
use Oasis\Mlib\Utils\ArrayDataProvider;
use Oasis\Mlib\Utils\DataProviderInterface;
use Pimple\ServiceProviderInterface;
use Silex\Application as SilexApp;
use Silex\CallbackResolver;
use Silex\Provider\ServiceControllerServiceProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Firewall;
use Twig_Environment;

/**
 * Class SilexKernel
 *
 * @package Oasis\Mlib\Http
 *
 *
 * @property-write array $service_providers array of ServiceProviderInterface,
 *                                          or a tube of <ServiceProviderInterface, parameters>
 * @property-write array $middlewares
 * @property-write array $view_handlers
 * @property-write array $error_handlers
 * @property-write array $injected_args
 * @property-write array $trusted_proxies
 * @property-write array $trusted_header_set
 */
class SilexKernel extends SilexApp implements AuthorizationCheckerInterface
{
    use ConfigurationValidationTrait;
    use SilexApp\TwigTrait;
    use SilexApp\UrlGeneratorTrait;
    
    const BEFORE_PRIORITY_EARLIEST = self::EARLY_EVENT;
    /** @see RouterListener */
    const BEFORE_PRIORITY_ROUTING = 32;
    /** @see CrossOriginResourceSharingProvider */
    const BEFORE_PRIORITY_CORS_PREFLIGHT = 20;
    /** @see Firewall */
    const BEFORE_PRIORITY_FIREWALL = 8;
    const BEFORE_PRIORITY_LATEST   = self::LATE_EVENT;
    
    const AFTER_PRIORITY_EARLIEST = self::EARLY_EVENT;
    const AFTER_PRIORITY_LATEST   = self::LATE_EVENT;
    
    /** @var  ArrayDataProvider */
    protected $httpDataProvider;
    /** @var bool */
    protected $isDebug = true;
    /** @var string|null */
    protected $cacheDir               = null;
    protected $controllerInjectedArgs = [];
    protected $extraParameters        = [];
    
    public function __construct(array $httpConfig, $isDebug)
    {
        parent::__construct();
        
        $this->httpDataProvider = $this->processConfiguration($httpConfig, new HttpConfiguration());
        $this->isDebug          = $isDebug;
        $this->cacheDir         = $this->httpDataProvider->getOptional('cache_dir');
        
        if ($isDebug) {
            $this['logger'] = MLogging::getLogger();
        }
        $this['debug'] = $this->isDebug;
        
        if (!isset($this['request'])) {
            $this['request'] = $this->factory(
                function ($app) {
                    /** @var RequestStack $stack */
                    $stack = $app['request_stack'];
                    
                    return $stack->getCurrentRequest();
                }
            );
        }
        
        $this['resolver_auto_injections'] = function () {
            return $this->controllerInjectedArgs;
        };
        $this['argument_value_resolvers'] = $this->extend(
            'argument_value_resolvers',
            function (array $resolvers,
                /** @noinspection PhpUnusedParameterInspection */
                      $app) {
                $resolvers = \array_merge(
                    [new ExtendedArgumentValueResolver($this['resolver_auto_injections'])],
                    $resolvers
                );
                
                return $resolvers;
            }
        );
        
        /*
         * Minimum number of milliseconds required to consider a request slow request
         */
        $this['slow_request_threshold'] = 5000;
        /*
         * A handler which will be called when a request is considered slow request
         *
         * Signature: $slowHandler(Request $request, $startTime, $responseSentTime, $endTime)
         *
         * All time values are in seconds (float)
         */
        $this['slow_request_handler'] = $this->protect(
            function (Request $request,
                      $startTime,
                      $responseSentTime,
                      $endTime) {
                mwarning(
                    "Slow request encountered, total = %.3f, http = %.3f, url = %s",
                    ($endTime - $startTime),
                    ($responseSentTime - $startTime),
                    $request->getUri()
                );
            }
        );
        
        // providers with built-in support
        if ($routingConfig = $this->httpDataProvider->getOptional('routing', DataProviderInterface::ARRAY_TYPE, [])) {
            if ($this->cacheDir) {
                $routingConfig = array_merge(['cache_dir' => $this->cacheDir], $routingConfig);
            }
        }
        $this['routing.config'] = $routingConfig;
        
        if ($twigConfig = $this->httpDataProvider->getOptional('twig', DataProviderInterface::ARRAY_TYPE, [])) {
            if ($this->cacheDir) {
                $twigConfig = array_merge(['cache_dir' => $this->cacheDir], $twigConfig);
            }
        }
        $this['twig.config'] = $twigConfig;
        
        $this['security.config'] = $this->httpDataProvider->getOptional(
            'security',
            DataProviderInterface::ARRAY_TYPE,
            []
        );
        
        $this['cors.strategies'] = $this->httpDataProvider->getOptional('cors', DataProviderInterface::ARRAY_TYPE, []);
        
        // other configuration settings
        if ($trustedProxiesConfig = $this->httpDataProvider->getOptional(
            'trusted_proxies',
            DataProviderInterface::MIXED_TYPE
        )
        ) {
            $this->trusted_proxies = $trustedProxiesConfig;
        }
        if ($trustedHeaderSet = $this->httpDataProvider->getOptional(
            'trusted_header_set',
            DataProviderInterface::MIXED_TYPE
        )
        ) {
            $this->trusted_header_set = $trustedHeaderSet;
        }
        if ($viewHandlersConfig = $this->httpDataProvider->getOptional(
            'view_handlers',
            DataProviderInterface::MIXED_TYPE
        )
        ) {
            $this->view_handlers = $viewHandlersConfig;
        }
        if ($errorHandlersConfig = $this->httpDataProvider->getOptional(
            'error_handlers',
            DataProviderInterface::MIXED_TYPE
        )
        ) {
            $this->error_handlers = $errorHandlersConfig;
        }
        if ($middlewaresConfig = $this->httpDataProvider->getOptional(
            'middlewares',
            DataProviderInterface::MIXED_TYPE
        )
        ) {
            $this->middlewares = $middlewaresConfig;
        }
        if ($providersConfig = $this->httpDataProvider->getOptional(
            'providers',
            DataProviderInterface::MIXED_TYPE
        )
        ) {
            $this->service_providers = $providersConfig;
        }
        if ($injectedArgs = $this->httpDataProvider->getOptional(
            'injected_args',
            DataProviderInterface::MIXED_TYPE
        )
        ) {
            $this->injected_args = $injectedArgs;
        }
    }
    
    function __set($name, $value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        switch ($name) {
            case 'trusted_proxies' : {
                Request::setTrustedProxies($value, Request::getTrustedHeaderSet());
            }
                break;
            case 'trusted_header_set' : {
                $headerSet = \current($value);
                if (\is_string($headerSet) && \constant(Request::class . "::" . $headerSet) !== null) {
                    $headerSet = \constant(Request::class . "::" . $headerSet);
                }
                Request::setTrustedProxies(Request::getTrustedProxies(), $headerSet);
            }
                break;
            case 'service_providers': {
                if (sizeof(
                        $providers = array_filter(
                            $value,
                            function ($v) {
                                return ($v instanceof ServiceProviderInterface
                                        || (is_array($v)
                                            && sizeof($v) == 2
                                            && $v[0] instanceof ServiceProviderInterface
                                        )
                                );
                            }
                        )
                    ) != sizeof($value)
                ) {
                    throw new InvalidConfigurationException("$name must be an array of ServiceProvider");
                };
                foreach ($providers as $provider) {
                    if ($provider instanceof ServiceProviderInterface) {
                        $this->register($provider);
                    }
                    else {
                        $this->register($provider[0], $provider[1]);
                    }
                }
            }
                break;
            case 'middlewares': {
                if (sizeof(
                        $middlewares = array_filter(
                            $value,
                            function ($v) {
                                return $v instanceof MiddlewareInterface;
                            }
                        )
                    ) != sizeof($value)
                ) {
                    throw new InvalidConfigurationException("$name must be an array of Middleware");
                };
                /** @var MiddlewareInterface $provider */
                foreach ($middlewares as $middleware) {
                    $this->addMiddleware($middleware);
                }
            }
                break;
            case 'view_handlers': {
                if (sizeof(
                        $viewHandlers = array_filter(
                            $value,
                            function ($v) {
                                return is_callable($v);
                            }
                        )
                    ) != sizeof($value)
                ) {
                    throw new InvalidConfigurationException("$name must be an array of Callable");
                };
                /** @var callable $viewHandler */
                foreach ($viewHandlers as $viewHandler) {
                    $this->view($viewHandler);
                }
            }
                break;
            case 'error_handlers': {
                if (sizeof(
                        $errorHandlers = array_filter(
                            $value,
                            function ($v) {
                                return is_callable($v);
                            }
                        )
                    ) != sizeof($value)
                ) {
                    throw new InvalidConfigurationException("$name must be an array of Callable");
                };
                /** @var callable $errorHandler */
                foreach ($errorHandlers as $errorHandler) {
                    $this->error($errorHandler);
                }
            }
                break;
            case 'injected_args': {
                foreach ($value as $arg) {
                    $this->addControllerInjectedArg($arg);
                }
            }
                break;
            default:
                throw new \LogicException("Invalid property $name set to SilexKernel");
        }
    }
    
    public function addControllerInjectedArg($object)
    {
        $this->controllerInjectedArgs[] = $object;
    }
    
    public function addExtraParameters($extras)
    {
        $this->extraParameters = array_merge($this->extraParameters, $extras);
    }
    
    public function addMiddleware(MiddlewareInterface $middleware)
    {
        if (false !== ($priority = $middleware->getBeforePriority())) {
            $this->before([$middleware, 'before'], $priority, $middleware->onlyForMasterRequest());
        }
        if (false !== ($priority = $middleware->getAfterPriority())) {
            $this->after([$middleware, 'after'], $priority, $middleware->onlyForMasterRequest());
        }
    }
    
    /**
     * @override Overrides parent function to enable before middleware for SUB_REQUEST
     *
     * Registers an after filter.
     *
     * After filters are run after the controller has been executed.
     *
     * @param mixed $callback          After filter callback
     * @param int   $priority          The higher this value, the earlier an event
     *                                 listener will be triggered in the chain (defaults to 0)
     * @param bool  $masterRequestOnly If this middleware is only applicable for Master Request
     */
    public function after($callback, $priority = 0, $masterRequestOnly = true)
    {
        $app = $this;
        
        $this->on(
            KernelEvents::RESPONSE,
            function (FilterResponseEvent $event) use ($callback, $app, $masterRequestOnly) {
                if ($masterRequestOnly && HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
                    return;
                }
                
                /** @var CallbackResolver $resolver */
                $resolver = $app['callback_resolver'];
                $response = call_user_func(
                    $resolver->resolveCallback($callback),
                    $event->getRequest(),
                    $event->getResponse(),
                    $app
                );
                if ($response instanceof Response) {
                    $event->setResponse($response);
                }
                elseif (null !== $response) {
                    throw new \RuntimeException(
                        'An after middleware returned an invalid response value. Must return null or an instance of Response.'
                    );
                }
            },
            $priority
        );
    }
    
    /**
     * @override Overrides parent function to enable before middleware for SUB_REQUEST
     *
     * Registers a before filter.
     *
     * Before filters are run before any route has been matched.
     *
     * @param mixed $callback          Before filter callback
     * @param int   $priority          The higher this value, the earlier an event
     *                                 listener will be triggered in the chain (defaults to 0)
     * @param bool  $masterRequestOnly If this middleware is only applicable for Master Request
     */
    public function before($callback, $priority = 0, $masterRequestOnly = true)
    {
        $app = $this;
        
        $this->on(
            KernelEvents::REQUEST,
            function (GetResponseEvent $event) use ($callback, $app, $masterRequestOnly) {
                if ($masterRequestOnly && HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
                    return;
                }
                
                /** @var CallbackResolver $resolver */
                $resolver = $app['callback_resolver'];
                $ret      = call_user_func(
                    $resolver->resolveCallback($callback),
                    $event->getRequest(),
                    $app
                );
                
                if ($ret instanceof Response) {
                    $event->setResponse($ret);
                }
            },
            $priority
        );
    }
    
    public function boot()
    {
        if ($this->booted) {
            return;
        }
        
        $this->register(new ServiceControllerServiceProvider());
        $this->register(new SimpleCookieProvider());
        $this->register(new CrossOriginResourceSharingProvider());
        if ($this['routing.config']) {
            $this->register(new CacheableRouterProvider());
        }
        if ($this['twig.config']) {
            $this->register(new SimpleTwigServiceProvider());
        }
        if ($this['security.config']) {
            // registering security provider without config will make twig provider fail
            $this->register(new SimpleSecurityProvider());
        }
        
        parent::boot(); // TODO: Change the autogenerated stub
    }
    
    /**
     * @override Overrides parent function to disable ensureResponse if exception is not handled
     *
     * @param mixed $callback
     * @param int   $priority
     */
    public function error($callback, $priority = -8)
    {
        $this->on(KernelEvents::EXCEPTION, new ExtendedExceptionListnerWrapper($this, $callback), $priority);
    }
    
    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied object.
     *
     * @param mixed $attributes
     * @param mixed $object
     *
     * @return bool
     */
    public function isGranted($attributes, $object = null)
    {
        if (!$this->offsetExists('security.authorization_checker')) {
            return false;
        }
        
        $checker = $this['security.authorization_checker'];
        if ($checker instanceof AuthorizationCheckerInterface) {
            try {
                return $checker->isGranted($attributes, $object);
            } catch (AuthenticationCredentialsNotFoundException $e) {
                // authentication not found is considered not granted,
                // there is no need to throw an exception out in this case
                mdebug("Authentication credential not found, isGranted will return false. msg = %s", $e->getMessage());
                
                return false;
            }
        }
        else {
            return false;
        }
    }
    
    public function run(Request $request = null)
    {
        $startTime = microtime(true);
        
        if (null === $request) {
            $request = Request::createFromGlobals();
        }
        
        $response = $this->handle($request);
        $response->send();
        $responseSentTime = microtime(true);
        
        $this->terminate($request, $response);
        
        $endTime = microtime(true);
        
        if ($endTime - $startTime > $this['slow_request_threshold'] / 1000) {
            call_user_func($this['slow_request_handler'], $request, $startTime, $responseSentTime, $endTime);
        }
    }
    
    public function getCacheDirectories()
    {
        $ret = [];
        if ($this->cacheDir) {
            $ret[] = $this->cacheDir;
        }
        if ($cacheDir = $this->httpDataProvider->getOptional('routing.cache_dir')) {
            $ret[] = $cacheDir;
        }
        if ($cacheDir = $this->httpDataProvider->getOptional('twig.cache_dir')) {
            $ret[] = $cacheDir;
        }
        
        return $ret;
    }
    
    public function getParameter($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this[$key];
        }
        elseif (array_key_exists($key, $this->extraParameters)) {
            return $this->extraParameters[$key];
        }
        else {
            return $default;
        }
    }
    
    /**
     * @return null|TokenInterface
     */
    public function getToken()
    {
        if (!$this->offsetExists('security.token_storage')) {
            return null;
        }
        
        $tokenStorage = $this['security.token_storage'];
        if ($tokenStorage instanceof TokenStorageInterface) {
            return $tokenStorage->getToken();
        }
        else {
            return null;
        }
    }
    
    /**
     * @return Twig_Environment|null
     */
    public function getTwig()
    {
        if (isset($this['twig'])) {
            return $this['twig'];
        }
        else {
            return null;
        }
    }
    
    /**
     * @return UserInterface|null
     */
    public function getUser()
    {
        $token = $this->getToken();
        if ($token instanceof TokenInterface) {
            return $token->getUser();
        }
        else {
            return null;
        }
    }
    
}
