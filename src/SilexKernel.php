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
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterUrlGeneratorProvider;
use Oasis\Mlib\Http\ServiceProviders\Security\SimpleSecurityProvider;
use Oasis\Mlib\Logging\MLogging;
use Oasis\Mlib\Utils\ArrayDataProvider;
use Oasis\Mlib\Utils\DataProviderInterface;
use Silex\Application as SilexApp;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\ServiceProviderInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
 */
class SilexKernel extends SilexApp implements AuthorizationCheckerInterface
{
    use ConfigurationValidationTrait;

    /** @var  ArrayDataProvider */
    protected $httpDataProvider;
    /** @var bool */
    protected $isDebug = true;
    
    public function __construct(array $httpConfig, $isDebug)
    {
        parent::__construct();

        $this['logger'] = MLogging::getLogger();

        $this->httpDataProvider = $this->processConfiguration($httpConfig, new HttpConfiguration());
        $this->isDebug          = $isDebug;
        
        $this->register(new ServiceControllerServiceProvider());

        if ($routingConfig = $this->httpDataProvider->getOptional('routing', DataProviderInterface::ARRAY_TYPE, [])) {
            $this->register($routerProvider = new CacheableRouterProvider($routingConfig, $this->isDebug));
            $this->register(new CacheableRouterUrlGeneratorProvider($routerProvider));
        }

        if ($securityConfig = $this->httpDataProvider->getOptional('security', DataProviderInterface::ARRAY_TYPE, [])) {
            $this->register(new SimpleSecurityProvider($securityConfig));
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

    public function addMiddleware(MiddlewareInterface $middleware)
    {
        if (false !== ($priority = $middleware->getBeforePriority())) {
            $this->before([$middleware, 'before'], $priority);
        }
        if (false !== ($priority = $middleware->getAfterPriority())) {
            $this->after([$middleware, 'after'], $priority);
        }
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
            default:
                throw new \LogicException("Invalid property $name set to SilexKernel");
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
        $checker = $this['security.authorization_checker'];
        if ($checker instanceof AuthorizationCheckerInterface) {
            return $checker->isGranted($attributes, $object);
        }
        else {
            // TODO: should we throw an exception?
            return false;
        }
    }

    /**
     * @return null|TokenInterface
     */
    public function getToken()
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this['security.token_storage'];

        return $tokenStorage->getToken();
    }

    /**
     * @return UserInterface|null
     */
    public function getUser()
    {
        $token = $this->getToken();
        if (is_null($token)) {
            return null;
        }
        else {
            return $token->getUser();
        }
    }
}
