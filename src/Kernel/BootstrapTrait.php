<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Kernel;

use Oasis\Mlib\Http\Middlewares\MiddlewareInterface;
use Oasis\Mlib\Utils\DataType;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Bootstrap configuration parsing extracted from MicroKernel.
 */
trait BootstrapTrait
{
    protected function parseBootstrapConfig(): void
    {
        if ($trustedProxiesConfig = $this->httpDataProvider->getOptional('trusted_proxies', DataType::Mixed)) {
            if (!is_array($trustedProxiesConfig)) {
                $trustedProxiesConfig = [$trustedProxiesConfig];
            }
            Request::setTrustedProxies(
                \array_merge(Request::getTrustedProxies(), $trustedProxiesConfig),
                Request::getTrustedHeaderSet()
            );
        }

        if ($trustedHeaderSet = $this->httpDataProvider->getOptional('trusted_header_set', DataType::Mixed)) {
            if (\is_string($trustedHeaderSet) && \constant(Request::class . "::" . $trustedHeaderSet) !== null) {
                $trustedHeaderSet = \constant(Request::class . "::" . $trustedHeaderSet);
            }
            Request::setTrustedProxies(Request::getTrustedProxies(), $trustedHeaderSet);
        }

        if ($middlewaresConfig = $this->httpDataProvider->getOptional('middlewares', DataType::Mixed)) {
            if (!is_array($middlewaresConfig)) {
                $middlewaresConfig = [$middlewaresConfig];
            }
            $filtered = array_filter($middlewaresConfig, fn($v) => $v instanceof MiddlewareInterface);
            if (\count($filtered) !== \count($middlewaresConfig)) {
                throw new InvalidConfigurationException("middlewares must be an array of Middleware");
            }
            foreach ($middlewaresConfig as $middleware) {
                $this->addMiddleware($middleware);
            }
        }

        if ($viewHandlersConfig = $this->httpDataProvider->getOptional('view_handlers', DataType::Mixed)) {
            if (!is_array($viewHandlersConfig)) {
                $viewHandlersConfig = [$viewHandlersConfig];
            }
            $filtered = array_filter($viewHandlersConfig, fn($v) => is_callable($v));
            if (\count($filtered) !== \count($viewHandlersConfig)) {
                throw new InvalidConfigurationException("view_handlers must be an array of Callable");
            }
            $this->viewHandlers = $viewHandlersConfig;
        }

        if ($errorHandlersConfig = $this->httpDataProvider->getOptional('error_handlers', DataType::Mixed)) {
            if (!is_array($errorHandlersConfig)) {
                $errorHandlersConfig = [$errorHandlersConfig];
            }
            $filtered = array_filter($errorHandlersConfig, fn($v) => is_callable($v));
            if (\count($filtered) !== \count($errorHandlersConfig)) {
                throw new InvalidConfigurationException("error_handlers must be an array of Callable");
            }
            $this->errorHandlers = $errorHandlersConfig;
        }

        if ($injectedArgs = $this->httpDataProvider->getOptional('injected_args', DataType::Mixed)) {
            if (!is_array($injectedArgs)) {
                $injectedArgs = [$injectedArgs];
            }
            foreach ($injectedArgs as $arg) {
                $this->addControllerInjectedArg($arg);
            }
        }

        if ($providersConfig = $this->httpDataProvider->getOptional('providers', DataType::Mixed)) {
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
}
