<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-01
 * Time: 10:30
 */

namespace Oasis\Mlib\Http;

use InvalidArgumentException;
use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\HttpConfiguration;
use Oasis\Mlib\Http\Middlewares\MiddlewareInterface;
use Oasis\Mlib\Http\ServiceProviders\Cookie\SimpleCookieProvider;
use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterUrlGeneratorProvider;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Http\ServiceProviders\Twig\SimpleTwigServiceProvider;
use Oasis\Mlib\Logging\MLogging;
use Oasis\Mlib\Utils\ArrayDataProvider;
use Oasis\Mlib\Utils\DataProviderInterface;
use Silex\Application as SilexApp;
use Silex\CallbackResolver;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\ServiceProviderInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
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
 */
class SilexKernel extends SilexApp implements AuthorizationCheckerInterface
{
    use ConfigurationValidationTrait;
    use SilexApp\TwigTrait;
    use SilexApp\UrlGeneratorTrait;
    
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
        
        $this['logger'] = MLogging::getLogger();
        $this['debug']  = $this->isDebug;
        
        $this['resolver']                 = $this->share(
            function () {
                return new ExtendedControllerResolver($this, $this['logger'], $this['resolver_auto_injections']);
            }
        );
        $this['resolver_auto_injections'] = $this->share(
            function () {
                return $this->controllerInjectedArgs;
            }
        );
        
        $this->register(new ServiceControllerServiceProvider());
        $this->register(new SimpleCookieProvider());
        
        // providers with built-in support
        if ($routingConfig = $this->httpDataProvider->getOptional('routing', DataProviderInterface::ARRAY_TYPE, [])) {
            if ($this->cacheDir) {
                $routingConfig = array_merge(['cache_dir' => $this->cacheDir], $routingConfig);
            }
        }
        $this['routing'] = $routingConfig;
        $this->register(new CacheableRouterProvider());
        $this->register(new CacheableRouterUrlGeneratorProvider());
        
        if ($twigConfig = $this->httpDataProvider->getOptional('twig', DataProviderInterface::ARRAY_TYPE, [])) {
            if ($this->cacheDir) {
                $twigConfig = array_merge(['cache_dir' => $this->cacheDir], $twigConfig);
            }
        }
        $this['twig.config'] = $twigConfig;
        $this->register(new SimpleTwigServiceProvider());
        
        if ($securityConfig = $this->httpDataProvider->getOptional('security', DataProviderInterface::ARRAY_TYPE, [])) {
            $this->register(new SimpleSecurityProvider($securityConfig));
        }
        
        if ($corsConfig = $this->httpDataProvider->getOptional('cors', DataProviderInterface::ARRAY_TYPE, [])) {
            $this->register(new CrossOriginResourceSharingProvider($corsConfig));
        }
        
        // other configuration settings
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
    
    public function addControllerInjectedArg($object)
    {
        $this->controllerInjectedArgs[] = $object;
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
     * Returns a closure that calls the service definition every time it is called. Hence acting as a
     * factory provider. Object returned by service definition is not unique in any scope. This is different
     * compared against share()
     *
     * @param callable $callable A service definition to create object
     *
     * @return Closure The wrapped closure
     */
    public static function factory($callable)
    {
        if (!is_object($callable) || !method_exists($callable, '__invoke')) {
            throw new InvalidArgumentException('Service definition is not a Closure or invokable object.');
        }
        
        return function ($c) use ($callable) {
            return $callable($c);
        };
    }
    
    function __set($name, $value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        switch ($name) {
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
    
    public function addExtraParameters($extras)
    {
        $this->extraParameters = array_merge($this->extraParameters, $extras);
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
     * Checks if the attributes are granted against the current authentication token and optionally supplied object.
     *
     * @param mixed $attributes
     * @param mixed $object
     *
     * @return bool
     */
    public function isGranted($attributes, $object = null)
    {
        // TODO: should we throw an exception ?
        if (!$this->offsetExists('security.authorization_checker')) {
            return false;
        }
        
        $checker = $this['security.authorization_checker'];
        if ($checker instanceof AuthorizationCheckerInterface) {
            return $checker->isGranted($attributes, $object);
        }
        else {
            return false;
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
    
    /**
     * @return Twig_Environment|null
     */
    public function getTwig()
    {
        return $this['twig'];
    }
}
