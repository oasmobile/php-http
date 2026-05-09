<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\HttpConfiguration;
use Oasis\Mlib\Http\Kernel\BootstrapTrait;
use Oasis\Mlib\Http\Kernel\CloudfrontTrustedProxyResolver;
use Oasis\Mlib\Http\Kernel\ConvenienceTrait;
use Oasis\Mlib\Http\Kernel\ErrorHandlerTrait;
use Oasis\Mlib\Http\Kernel\MiddlewareTrait;
use Oasis\Mlib\Http\Kernel\RoutingTrait;
use Oasis\Mlib\Http\Kernel\SecurityTrait;
use Oasis\Mlib\Http\Kernel\ServicesTrait;
use Oasis\Mlib\Http\Middlewares\MiddlewareInterface;
use Oasis\Mlib\Http\ServiceProviders\Cors\CrossOriginResourceSharingProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouter;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterProvider;
use Oasis\Mlib\Utils\ArrayDataProvider;
use Oasis\Mlib\Utils\DataType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment as TwigEnvironment;

class MicroKernel extends Kernel implements AuthorizationCheckerInterface
{
    use MicroKernelTrait;
    use ConfigurationValidationTrait;
    use ConvenienceTrait;
    use RoutingTrait;
    use SecurityTrait;
    use ErrorHandlerTrait;
    use MiddlewareTrait;
    use BootstrapTrait;
    use ServicesTrait;

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
    /** @var array<object> */
    protected $controllerInjectedArgs = [];
    /** @var array<string, mixed> */
    protected $extraParameters        = [];
    /** @var MiddlewareInterface[] */
    protected $middlewares = [];
    /** @var callable[] */
    protected $viewHandlers = [];
    /** @var array<callable> */
    protected $errorHandlers = [];
    /** @var TwigEnvironment|null */
    protected $twigEnvironment = null;
    /** @var TokenStorageInterface|null */
    protected $tokenStorage = null;
    /** @var AuthorizationCheckerInterface|null */
    protected $authorizationChecker = null;
    /** @var array<CompilerPassInterface|ExtensionInterface> */
    protected $providers = [];
    /** @var CrossOriginResourceSharingProvider|null */
    protected $corsSubscriber = null;
    /** @var CacheableRouterProvider|null */
    protected $routerProvider = null;
    /** @var array<array{name: string, route: Route}|array{collection: RouteCollection}> */
    protected array $pendingRoutes = [];
    /** @var list<array{config: array<string, mixed>, allowOverwrite: bool}> */
    protected array $pendingSecurityConfigs = [];
    /** @var CacheableRouter|null Router for programmatic-only routes (no YAML config) */
    protected ?CacheableRouter $directRouter = null;
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

    /**
     * @param array<string, mixed> $httpConfig
     */
    public function __construct(array $httpConfig, bool $isDebug)
    {
        $this->httpDataProvider = $this->processConfiguration($httpConfig, new HttpConfiguration());
        $this->isDebug          = $isDebug;
        $this->cacheDir         = $this->httpDataProvider->getOptional('cache_dir');

        parent::__construct($isDebug ? 'dev' : 'prod', $isDebug);

        $this->parseBootstrapConfig();
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
            DataType::Bool
        )) {
            $trustedProxies   = Request::getTrustedProxies();
            $trustedProxies[] = $request->server->get('REMOTE_ADDR');
            Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_AWS_ELB);
        }

        if ($this->httpDataProvider->getMandatory(
            'trust_cloudfront_ips',
            DataType::Bool
        )) {
            $this->setCloudfrontTrustedProxies();
        }

        return parent::handle($request, $type, $catch);
    }

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied object.
     */
    public function isGranted(mixed $attributes, mixed $object = null, ?AccessDecision $accessDecision = null): bool
    {
        if ($this->authorizationChecker === null) {
            return false;
        }

        try {
            return $this->authorizationChecker->isGranted($attributes, $object, $accessDecision);
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

    /**
     * @param array<string, mixed> $extras
     */
    public function addExtraParameters(array $extras): void
    {
        $this->extraParameters = array_merge($this->extraParameters, $extras);
    }

    public function addControllerInjectedArg(object $object): void
    {
        $this->controllerInjectedArgs[] = $object;
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

        // Override the default Symfony logger to suppress stderr output.
        $loggerDef = new Definition(\Psr\Log\NullLogger::class);
        $loggerDef->setPublic(true);
        $container->setDefinition('logger', $loggerDef);

        // Register user-provided CompilerPass / Extension
        foreach ($this->providers as $provider) {
            if ($provider instanceof CompilerPassInterface) {
                $container->addCompilerPass($provider);
            } elseif ($provider instanceof ExtensionInterface) {
                $container->registerExtension($provider);
                $container->loadFromExtension($provider->getAlias());
            }
        }

        // Register ExtendedArgumentValueResolver for controller injected args.
        $resolverDef = new Definition(ExtendedArgumentValueResolver::class);
        $resolverDef->setFactory([new Reference('kernel'), 'createArgumentValueResolver']);
        $resolverDef->setPublic(true);
        $resolverDef->addTag('controller.argument_value_resolver', ['priority' => 200]);
        $container->setDefinition(ExtendedArgumentValueResolver::class, $resolverDef);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        parent::boot();

        // Register the kernel itself as a controller injected arg
        // so controllers can type-hint MicroKernel to receive it
        $this->addControllerInjectedArg($this);

        // Register Cookie subscriber
        $this->registerCookie();

        // Register CORS subscriber if cors config is provided (before routing,
        // so onPreRouting can detect preflight before the routing listener throws)
        $this->registerCors();

        // Register Twig environment if twig config is provided
        $this->registerTwig();

        // Register Security provider if security config is provided
        $this->registerSecurity();

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
        $resolver = $this->createCloudfrontTrustedProxyResolver();
        $resolver->resolve();
    }

    /**
     * Create the CloudFront trusted proxy resolver.
     * Override in tests to inject a mock resolver.
     */
    protected function createCloudfrontTrustedProxyResolver(): CloudfrontTrustedProxyResolver
    {
        return new CloudfrontTrustedProxyResolver($this->cacheDir);
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
     * @return Router|null The router, or null if routing is not configured and no programmatic routes exist.
     */
    public function getRouter(): ?Router
    {
        if ($this->routerProvider !== null) {
            return $this->routerProvider->getRouter(new RequestContext());
        }

        if ($this->directRouter !== null) {
            return $this->directRouter;
        }

        return null;
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
     * @return array<callable>
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

    /**
     * Factory method for creating the ExtendedArgumentValueResolver.
     * Called by the DI container via service factory definition.
     */
    public function createArgumentValueResolver(): ExtendedArgumentValueResolver
    {
        return new ExtendedArgumentValueResolver($this->controllerInjectedArgs);
    }

    // ─── Symfony Kernel overrides ────────────────────────────────────

    public function getProjectDir(): string
    {
        if ($projectDir = $this->httpDataProvider->getOptional('project_dir')) {
            return $projectDir;
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new \RuntimeException('Unable to determine the current working directory');
        }

        return $cwd;
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
        if ($logDir = $this->httpDataProvider->getOptional('log_dir')) {
            return $logDir;
        }

        return sys_get_temp_dir() . '/oasis_http_logs';
    }

    public function registerBundles(): iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
        ];
    }
}
