<?php

namespace Oasis\Mlib\Http;

use GuzzleHttp\Client;
use Oasis\Mlib\Http\Configuration\CacheableRouterConfiguration;
use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\HttpConfiguration;
use Oasis\Mlib\Http\Middlewares\MiddlewareInterface;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlGenerator;
use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlMatcher;
use Oasis\Mlib\Logging\MLogging;
use Oasis\Mlib\Utils\ArrayDataProvider;
use Oasis\Mlib\Utils\DataProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment as TwigEnvironment;

class MicroKernel extends Kernel implements AuthorizationCheckerInterface
{
    use MicroKernelTrait;
    use ConfigurationValidationTrait;

    // Priority constants — 精确数值是行为契约（CR Q3: A）
    const BEFORE_PRIORITY_EARLIEST       = 512;
    const BEFORE_PRIORITY_ROUTING        = 32;
    const BEFORE_PRIORITY_CORS_PREFLIGHT = 20;
    const BEFORE_PRIORITY_FIREWALL       = 8;
    const BEFORE_PRIORITY_LATEST         = -512;
    const AFTER_PRIORITY_EARLIEST        = 512;
    const AFTER_PRIORITY_LATEST          = -512;

    /** @var ArrayDataProvider */
    protected $httpDataProvider;
    /** @var bool */
    protected $isDebug = true;
    /** @var string|null */
    protected $cacheDir               = null;
    protected $controllerInjectedArgs = [];
    protected $extraParameters        = [];
    /** @var MiddlewareInterface[] */
    protected $middlewares = [];
    /** @var callable[] */
    protected $viewHandlers = [];
    /** @var array */
    protected $errorHandlers = [];
    /** @var TwigEnvironment|null */
    protected $twigEnvironment = null;
    /** @var TokenStorageInterface|null */
    protected $tokenStorage = null;
    /** @var AuthorizationCheckerInterface|null */
    protected $authorizationChecker = null;
    /** @var array */
    protected $providers = [];
    /** @var CacheableRouterProvider|null */
    protected $routerProvider = null;
    /** @var ArrayDataProvider|null */
    protected $routingConfigDataProvider = null;
    /** @var RequestMatcherInterface|null */
    protected $requestMatcher = null;
    /** @var UrlGeneratorInterface|null */
    protected $urlGenerator = null;

    /**
     * Slow request threshold in milliseconds.
     */
    protected int $slowRequestThreshold = 5000;

    /**
     * Slow request handler callable.
     * Signature: function(Request $request, float $startTime, float $responseSentTime, float $endTime): void
     *
     * @var callable|null
     */
    protected $slowRequestHandler = null;

    public function __construct(array $httpConfig, bool $isDebug)
    {
        $this->httpDataProvider = $this->processConfiguration($httpConfig, new HttpConfiguration());
        $this->isDebug          = $isDebug;
        $this->cacheDir         = $this->httpDataProvider->getOptional('cache_dir');

        parent::__construct($isDebug ? 'dev' : 'prod', $isDebug);

        $this->parseBootstrapConfig();
    }

    /**
     * Parse Bootstrap_Config keys and store them as instance properties.
     */
    protected function parseBootstrapConfig(): void
    {
        // trusted_proxies
        if ($trustedProxiesConfig = $this->httpDataProvider->getOptional(
            'trusted_proxies',
            DataProviderInterface::MIXED_TYPE
        )) {
            if (!is_array($trustedProxiesConfig)) {
                $trustedProxiesConfig = [$trustedProxiesConfig];
            }
            Request::setTrustedProxies(
                \array_merge(Request::getTrustedProxies(), $trustedProxiesConfig),
                Request::getTrustedHeaderSet()
            );
        }

        // trusted_header_set
        if ($trustedHeaderSet = $this->httpDataProvider->getOptional(
            'trusted_header_set',
            DataProviderInterface::MIXED_TYPE
        )) {
            if (\is_string($trustedHeaderSet) && \constant(Request::class . "::" . $trustedHeaderSet) !== null) {
                $trustedHeaderSet = \constant(Request::class . "::" . $trustedHeaderSet);
            }
            Request::setTrustedProxies(Request::getTrustedProxies(), $trustedHeaderSet);
        }

        // middlewares
        if ($middlewaresConfig = $this->httpDataProvider->getOptional(
            'middlewares',
            DataProviderInterface::MIXED_TYPE
        )) {
            if (!is_array($middlewaresConfig)) {
                $middlewaresConfig = [$middlewaresConfig];
            }
            $filtered = array_filter(
                $middlewaresConfig,
                function ($v) {
                    return $v instanceof MiddlewareInterface;
                }
            );
            if (\count($filtered) !== \count($middlewaresConfig)) {
                throw new InvalidConfigurationException("middlewares must be an array of Middleware");
            }
            foreach ($middlewaresConfig as $middleware) {
                $this->addMiddleware($middleware);
            }
        }

        // view_handlers
        if ($viewHandlersConfig = $this->httpDataProvider->getOptional(
            'view_handlers',
            DataProviderInterface::MIXED_TYPE
        )) {
            if (!is_array($viewHandlersConfig)) {
                $viewHandlersConfig = [$viewHandlersConfig];
            }
            $filtered = array_filter(
                $viewHandlersConfig,
                function ($v) {
                    return is_callable($v);
                }
            );
            if (\count($filtered) !== \count($viewHandlersConfig)) {
                throw new InvalidConfigurationException("view_handlers must be an array of Callable");
            }
            $this->viewHandlers = $viewHandlersConfig;
        }

        // error_handlers
        if ($errorHandlersConfig = $this->httpDataProvider->getOptional(
            'error_handlers',
            DataProviderInterface::MIXED_TYPE
        )) {
            if (!is_array($errorHandlersConfig)) {
                $errorHandlersConfig = [$errorHandlersConfig];
            }
            $filtered = array_filter(
                $errorHandlersConfig,
                function ($v) {
                    return is_callable($v);
                }
            );
            if (\count($filtered) !== \count($errorHandlersConfig)) {
                throw new InvalidConfigurationException("error_handlers must be an array of Callable");
            }
            $this->errorHandlers = $errorHandlersConfig;
        }

        // injected_args
        if ($injectedArgs = $this->httpDataProvider->getOptional(
            'injected_args',
            DataProviderInterface::MIXED_TYPE
        )) {
            if (!is_array($injectedArgs)) {
                $injectedArgs = [$injectedArgs];
            }
            foreach ($injectedArgs as $arg) {
                $this->addControllerInjectedArg($arg);
            }
        }

        // providers (CompilerPassInterface / ExtensionInterface)
        if ($providersConfig = $this->httpDataProvider->getOptional(
            'providers',
            DataProviderInterface::MIXED_TYPE
        )) {
            if (!is_array($providersConfig)) {
                $providersConfig = [$providersConfig];
            }
            foreach ($providersConfig as $provider) {
                if (!($provider instanceof CompilerPassInterface) && !($provider instanceof ExtensionInterface)) {
                    throw new InvalidConfigurationException(
                        'providers must be an array of CompilerPassInterface or ExtensionInterface'
                    );
                }
            }
            $this->providers = $providersConfig;
        }
    }

    // ─── Public API ──────────────────────────────────────────────────

    public function run(?Request $request = null): void
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

        if ($endTime - $startTime > $this->slowRequestThreshold / 1000) {
            $handler = $this->slowRequestHandler;
            if ($handler !== null) {
                $handler($request, $startTime, $responseSentTime, $endTime);
            } else {
                mwarning(
                    "Slow request encountered, total = %.3f, http = %.3f, url = %s",
                    ($endTime - $startTime),
                    ($responseSentTime - $startTime),
                    $request->getUri()
                );
            }
        }
    }

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        if ($this->httpDataProvider->getMandatory(
            'behind_elb',
            DataProviderInterface::BOOL_TYPE
        )) {
            $trustedProxies   = Request::getTrustedProxies();
            $trustedProxies[] = $request->server->get('REMOTE_ADDR');
            Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_AWS_ELB);
        }

        if ($this->httpDataProvider->getMandatory(
            'trust_cloudfront_ips',
            DataProviderInterface::BOOL_TYPE
        )) {
            $this->setCloudfrontTrustedProxies();
        }

        return parent::handle($request, $type, $catch);
    }

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied object.
     */
    public function isGranted(mixed $attributes, mixed $object = null): bool
    {
        if ($this->authorizationChecker === null) {
            return false;
        }

        try {
            return $this->authorizationChecker->isGranted($attributes, $object);
        } catch (AuthenticationCredentialsNotFoundException $e) {
            mdebug("Authentication credential not found, isGranted will return false. msg = %s", $e->getMessage());

            return false;
        }
    }

    public function getToken(): ?TokenInterface
    {
        if ($this->tokenStorage === null) {
            return null;
        }

        return $this->tokenStorage->getToken();
    }

    public function getUser(): ?UserInterface
    {
        $token = $this->getToken();
        if ($token instanceof TokenInterface) {
            return $token->getUser();
        }

        return null;
    }

    public function getTwig(): ?TwigEnvironment
    {
        return $this->twigEnvironment;
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->extraParameters)) {
            return $this->extraParameters[$key];
        }

        return $default;
    }

    public function addExtraParameters(array $extras): void
    {
        $this->extraParameters = array_merge($this->extraParameters, $extras);
    }

    public function addControllerInjectedArg(object $object): void
    {
        $this->controllerInjectedArgs[] = $object;
    }

    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * @return string[]
     */
    public function getCacheDirectories(): array
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

    // ─── Internal: Middleware registration ────────────────────────────

    /**
     * Register all middlewares as EventDispatcher listeners.
     * Called during boot().
     */
    protected function registerMiddlewares(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        foreach ($this->middlewares as $middleware) {
            if (false !== ($priority = $middleware->getBeforePriority())) {
                $dispatcher->addListener(
                    KernelEvents::REQUEST,
                    function (RequestEvent $event) use ($middleware) {
                        if ($middleware->onlyForMasterRequest()
                            && $event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
                            return;
                        }
                        $ret = $middleware->before($event->getRequest(), $this);
                        if ($ret instanceof Response) {
                            $event->setResponse($ret);
                        }
                    },
                    $priority
                );
            }
            if (false !== ($priority = $middleware->getAfterPriority())) {
                $dispatcher->addListener(
                    KernelEvents::RESPONSE,
                    function (ResponseEvent $event) use ($middleware) {
                        if ($middleware->onlyForMasterRequest()
                            && $event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
                            return;
                        }
                        $middleware->after($event->getRequest(), $event->getResponse());
                    },
                    $priority
                );
            }
        }
    }

    // ─── Internal: Error handler registration ────────────────────────

    /**
     * Register error handlers as EventDispatcher listeners on KernelEvents::EXCEPTION.
     * Called during boot().
     */
    protected function registerErrorHandlers(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        foreach ($this->errorHandlers as $handler) {
            $dispatcher->addListener(
                KernelEvents::EXCEPTION,
                function (ExceptionEvent $event) use ($handler) {
                    $exception = $event->getThrowable();
                    $request   = $event->getRequest();
                    $code      = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                        ? $exception->getStatusCode()
                        : 500;

                    $response = $handler($exception, $request, $code);

                    if ($response instanceof Response) {
                        $event->setResponse($response);
                    }
                    // handler returns null and event has no response → let exception propagate
                },
                -8 // default priority, consistent with SilexKernel::error()
            );
        }
    }

    // ─── Internal: View handler registration ─────────────────────────

    /**
     * Register view handlers as EventDispatcher listener on KernelEvents::VIEW.
     * Called during boot().
     */
    protected function registerViewHandlers(): void
    {
        if (empty($this->viewHandlers)) {
            return;
        }

        $handlers   = $this->viewHandlers;
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addListener(
            KernelEvents::VIEW,
            function (\Symfony\Component\HttpKernel\Event\ViewEvent $event) use ($handlers) {
                $result  = $event->getControllerResult();
                $request = $event->getRequest();

                foreach ($handlers as $handler) {
                    $response = $handler($result, $request);
                    if ($response instanceof Response) {
                        $event->setResponse($response);
                        return;
                    }
                }
            },
            0
        );
    }

    // ─── Internal: Routing registration ─────────────────────────────

    /**
     * Register routing services if routing config is provided.
     * Called during boot().
     */
    protected function registerRouting(): void
    {
        $routingConfig = $this->httpDataProvider->getOptional(
            'routing',
            DataProviderInterface::MIXED_TYPE
        );

        if (!$routingConfig || !is_array($routingConfig)) {
            return;
        }

        // Process routing configuration
        $this->routingConfigDataProvider = $this->processConfiguration(
            $routingConfig,
            new CacheableRouterConfiguration()
        );

        // Create and register the router provider
        $this->routerProvider = new CacheableRouterProvider();
        $this->routerProvider->register($this);

        // Build routing services with a fresh RequestContext
        $requestContext       = new RequestContext();
        $this->requestMatcher = $this->routerProvider->buildRequestMatcher($requestContext);
        $this->urlGenerator   = $this->routerProvider->buildUrlGenerator($requestContext);
    }

    // ─── Symfony Kernel overrides ────────────────────────────────────

    public function getProjectDir(): string
    {
        // Return the working directory; MicroKernel is a library kernel, not a full app
        return getcwd();
    }

    public function getCacheDir(): string
    {
        if ($this->cacheDir) {
            return $this->cacheDir . '/symfony';
        }

        return sys_get_temp_dir() . '/oasis_http_' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/oasis_http_logs';
    }

    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
        ];
    }

    /**
     * Configure the Symfony DI container.
     * Translates Bootstrap_Config into Symfony DI service definitions.
     */
    protected function configureContainer(ContainerBuilder $container): void
    {
        // Framework bundle minimal configuration
        $container->loadFromExtension('framework', [
            'secret' => 'oasis_http_' . md5(__DIR__),
            'test'   => $this->isDebug,
            'router' => [
                'utf8' => true,
            ],
        ]);

        // Register user-provided CompilerPass / Extension
        foreach ($this->providers as $provider) {
            if ($provider instanceof CompilerPassInterface) {
                $container->addCompilerPass($provider);
            } elseif ($provider instanceof ExtensionInterface) {
                $container->registerExtension($provider);
                $container->loadFromExtension($provider->getAlias());
            }
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        parent::boot();

        // Register routing services if routing config is provided
        $this->registerRouting();

        // Register middlewares, view handlers, error handlers after container is ready
        $this->registerMiddlewares();
        $this->registerViewHandlers();
        $this->registerErrorHandlers();
    }

    // ─── Internal: CloudFront trusted proxies ────────────────────────

    protected function setCloudfrontTrustedProxies(): void
    {
        try {
            $awsIps = [];
            if ($this->cacheDir) {
                $cacheFilename = $this->cacheDir . "/aws.ips";
                if (\file_exists($cacheFilename)) {
                    $content = \file_get_contents($cacheFilename);
                    try {
                        $awsIps = \GuzzleHttp\json_decode($content, true);
                        if (isset($awsIps['expire_at']) && time() > $awsIps['expire_at']) {
                            $awsIps = [];
                        }
                    } catch (\Throwable $throwable) {
                        \merror(
                            "Error while processing cached ip file, exception = %s, file content = %s",
                            $throwable->getMessage(),
                            $content
                        );
                        $awsIps = [];
                    }
                }
            }

            if (!\array_key_exists('prefixes', $awsIps)) {
                $guzzleClient = new Client(
                    [
                        'base_uri' => 'https://ip-ranges.amazonaws.com/',
                        'timeout'  => 5.0,
                    ]
                );
                $awsResponse = $guzzleClient->request('GET', 'ip-ranges.json');
                if ($awsResponse->getStatusCode() != Response::HTTP_OK) {
                    \merror(
                        "Cannot get ip-ranges from aws server, response = %s %s, %s",
                        $awsResponse->getStatusCode(),
                        $awsResponse->getReasonPhrase(),
                        $awsResponse->getBody()->getContents()
                    );
                } else {
                    $content = $awsResponse->getBody()->getContents();
                    $awsIps  = \GuzzleHttp\json_decode($content, true);
                    if ($this->cacheDir && \is_writable($this->cacheDir)) {
                        $cacheFilename       = $this->cacheDir . "/aws.ips";
                        $awsIps['expire_at'] = time() + 86400;
                        \file_put_contents(
                            $cacheFilename,
                            \GuzzleHttp\json_encode($awsIps, \JSON_PRETTY_PRINT),
                            \LOCK_EX
                        );
                    }
                }
            }

            if (\is_array($awsIps) && \array_key_exists('prefixes', $awsIps)) {
                $trustedCloudfrontIps = [];
                foreach ($awsIps['prefixes'] as $info) {
                    if (\array_key_exists('ip_prefix', $info) && $info['service'] == "CLOUDFRONT") {
                        $trustedCloudfrontIps[] = $info['ip_prefix'];
                    }
                }
                Request::setTrustedProxies(
                    \array_merge(Request::getTrustedProxies(), $trustedCloudfrontIps),
                    Request::HEADER_X_FORWARDED_AWS_ELB
                );
            }
        } catch (\Throwable $throwable) {
            \merror("Error while setting aws trusted proxies, exception = %s", $throwable->getMessage());
        }
    }

    // ─── Accessors for internal state (used by service providers) ────

    public function getHttpDataProvider(): ArrayDataProvider
    {
        return $this->httpDataProvider;
    }

    /**
     * @return ArrayDataProvider|null The routing configuration data provider, or null if routing is not configured.
     */
    public function getRoutingConfigDataProvider(): ?ArrayDataProvider
    {
        return $this->routingConfigDataProvider;
    }

    /**
     * @return RequestMatcherInterface|null The request matcher, or null if routing is not configured.
     */
    public function getRequestMatcher(): ?RequestMatcherInterface
    {
        return $this->requestMatcher;
    }

    /**
     * @return UrlGeneratorInterface|null The URL generator, or null if routing is not configured.
     */
    public function getUrlGenerator(): ?UrlGeneratorInterface
    {
        return $this->urlGenerator;
    }

    /**
     * @return Router|null The router, or null if routing is not configured.
     */
    public function getRouter(): ?Router
    {
        if ($this->routerProvider === null) {
            return null;
        }

        return $this->routerProvider->getRouter(new RequestContext());
    }

    /**
     * @return object[]
     */
    public function getControllerInjectedArgs(): array
    {
        return $this->controllerInjectedArgs;
    }

    /**
     * @return callable[]
     */
    public function getViewHandlers(): array
    {
        return $this->viewHandlers;
    }

    /**
     * @return array
     */
    public function getErrorHandlers(): array
    {
        return $this->errorHandlers;
    }

    /**
     * Set the Twig environment (called by Twig provider during boot).
     */
    public function setTwigEnvironment(?TwigEnvironment $twig): void
    {
        $this->twigEnvironment = $twig;
    }

    /**
     * Set the token storage (called by Security provider during boot).
     */
    public function setTokenStorage(?TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Set the authorization checker (called by Security provider during boot).
     */
    public function setAuthorizationChecker(?AuthorizationCheckerInterface $checker): void
    {
        $this->authorizationChecker = $checker;
    }
}
