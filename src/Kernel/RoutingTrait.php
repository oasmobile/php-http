<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Kernel;

use Oasis\Mlib\Http\Configuration\CacheableRouterConfiguration;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouter;
use Oasis\Mlib\Http\ServiceProviders\Routing\CacheableRouterProvider;
use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlGenerator;
use Oasis\Mlib\Http\ServiceProviders\Routing\GroupUrlMatcher;
use Oasis\Mlib\Utils\DataType;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Routing registration logic extracted from MicroKernel.
 */
trait RoutingTrait
{
    public function addRoute(string $name, Route $route): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add routes after the kernel has been booted.');
        }

        $this->pendingRoutes[] = ['name' => $name, 'route' => $route];
    }

    public function addRoutes(RouteCollection $routes): void
    {
        if ($this->booted) {
            throw new \LogicException('Cannot add routes after the kernel has been booted.');
        }

        $this->pendingRoutes[] = ['collection' => $routes];
    }

    protected function registerRouting(): void
    {
        $routingConfig = $this->httpDataProvider->getOptional('routing', DataType::Mixed);
        $hasPendingRoutes = !empty($this->pendingRoutes);

        if ((!$routingConfig || !is_array($routingConfig)) && !$hasPendingRoutes) {
            return;
        }

        $requestContext = new RequestContext();

        if ($routingConfig && is_array($routingConfig)) {
            $this->routingConfigDataProvider = $this->processConfiguration(
                $routingConfig,
                new CacheableRouterConfiguration()
            );

            $routerProvider = new CacheableRouterProvider();
            $routerProvider->register($this);
            $this->routerProvider = $routerProvider;

            $matchers   = [];
            $generators = [];

            if ($hasPendingRoutes) {
                $programmaticCollection = new RouteCollection();
                $this->mergePendingRoutes($programmaticCollection);

                $programmaticMatcher = new UrlMatcher($programmaticCollection, $requestContext);
                $matchers[]   = $programmaticMatcher;
                $generators[] = new UrlGenerator($programmaticCollection, $requestContext);
            }

            $yamlMatcher = $routerProvider->buildRequestMatcher($requestContext);
            $matchers[]   = $yamlMatcher;
            $generators[] = $routerProvider->buildUrlGenerator($requestContext);

            $this->requestMatcher = new GroupUrlMatcher($requestContext, $matchers);
            $this->urlGenerator   = new GroupUrlGenerator($generators);

            $cacheableRouter = $routerProvider->getRouter($requestContext);
            assert($cacheableRouter instanceof CacheableRouter);
            if ($hasPendingRoutes) {
                $yamlCollection = $cacheableRouter->getRouteCollection();
                $this->mergePendingRoutes($yamlCollection);
            }

            $cacheableRouter->freeze();
        } else {
            $loader = new ClosureLoader();
            $directRouter = new CacheableRouter(
                $this,
                $loader,
                function () { return new RouteCollection(); },
                ['cache_dir' => null, 'debug' => $this->isDebug],
                $requestContext
            );
            $this->directRouter = $directRouter;

            $collection = $directRouter->getRouteCollection();
            $this->mergePendingRoutes($collection);

            $matcher = new UrlMatcher($collection, $requestContext);
            $this->requestMatcher = new GroupUrlMatcher($requestContext, [$matcher]);
            $this->urlGenerator = new GroupUrlGenerator(
                [new UrlGenerator($collection, $requestContext)]
            );

            $directRouter->freeze();
        }

        assert($this->requestMatcher !== null);
        $matcher = $this->requestMatcher;
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addListener(
            KernelEvents::REQUEST,
            function (RequestEvent $event) use ($matcher, $requestContext) {
                $request = $event->getRequest();

                if ($request->attributes->has('_controller')) {
                    return;
                }

                try {
                    $requestContext->fromRequest($request);
                    $parameters = $matcher->matchRequest($request);

                    $request->attributes->add($parameters);
                    unset($parameters['_route'], $parameters['_controller']);
                    $request->attributes->set('_route_params', $parameters);
                } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
                    $originalScheme = $requestContext->getScheme();
                    try {
                        $targetScheme = ($originalScheme === 'https') ? 'http' : 'https';
                        $requestContext->setScheme($targetScheme);
                        $parameters = $matcher->matchRequest($request);
                        $url = $targetScheme . '://' . $request->getHost() . $request->getBaseUrl() . $request->getPathInfo();
                        if ($request->getQueryString()) {
                            $url .= '?' . $request->getQueryString();
                        }
                        $event->setResponse(new \Symfony\Component\HttpFoundation\RedirectResponse($url, 302));
                        $event->stopPropagation();
                        return;
                    } catch (\Throwable $retryException) {
                    } finally {
                        $requestContext->setScheme($originalScheme);
                    }
                } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException $e) {
                    $message = \sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getBaseUrl().$request->getPathInfo(), implode(', ', $e->getAllowedMethods()));
                    throw new \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException($e->getAllowedMethods(), $message, $e);
                }
            },
            self::BEFORE_PRIORITY_ROUTING + 1
        );
    }

    private function mergePendingRoutes(RouteCollection $collection): void
    {
        foreach ($this->pendingRoutes as $entry) {
            if (isset($entry['collection'])) {
                $collection->addCollection($entry['collection']);
            } else {
                $collection->add($entry['name'], $entry['route']);
            }
        }
    }
}
