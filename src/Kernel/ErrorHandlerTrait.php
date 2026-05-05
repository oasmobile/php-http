<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Kernel;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Error handler registration logic extracted from MicroKernel.
 */
trait ErrorHandlerTrait
{
    protected function registerErrorHandlers(): void
    {
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $kernel = $this;
        foreach ($this->errorHandlers as $handler) {
            $dispatcher->addListener(
                KernelEvents::EXCEPTION,
                function (ExceptionEvent $event) use ($handler, $kernel) {
                    if ($event->getResponse() !== null) {
                        return;
                    }

                    $exception = $event->getThrowable();

                    if (!self::shouldRunErrorHandler($handler, $exception)) {
                        return;
                    }

                    $request = $event->getRequest();
                    $code    = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                        ? $exception->getStatusCode()
                        : 500;

                    $response = $handler($exception, $request, $code);

                    if ($response instanceof Response) {
                        $event->setResponse($response);
                    } elseif ($response !== null) {
                        foreach ($kernel->getViewHandlers() as $viewHandler) {
                            $viewResponse = $viewHandler($response, $request);
                            if ($viewResponse instanceof Response) {
                                $viewResponse->setStatusCode($code);
                                $event->setResponse($viewResponse);
                                return;
                            }
                        }
                    }
                },
                -8
            );
        }
    }

    protected function registerSingleErrorHandler(callable $handler, int $priority = -8): void
    {
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $kernel = $this;
        $dispatcher->addListener(
            KernelEvents::EXCEPTION,
            function (ExceptionEvent $event) use ($handler, $kernel) {
                if ($event->getResponse() !== null) {
                    return;
                }

                $exception = $event->getThrowable();

                if (!self::shouldRunErrorHandler($handler, $exception)) {
                    return;
                }

                $request = $event->getRequest();
                $code    = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    ? $exception->getStatusCode()
                    : 500;

                $response = $handler($exception, $request, $code);

                if ($response instanceof Response) {
                    $event->setResponse($response);
                } elseif ($response !== null) {
                    foreach ($kernel->getViewHandlers() as $viewHandler) {
                        $viewResponse = $viewHandler($response, $request);
                        if ($viewResponse instanceof Response) {
                            $viewResponse->setStatusCode($code);
                            $event->setResponse($viewResponse);
                            return;
                        }
                    }
                }
            },
            $priority
        );
    }

    private static function shouldRunErrorHandler(callable $handler, \Throwable $exception): bool
    {
        try {
            if (\is_array($handler)) {
                $reflection = new \ReflectionMethod($handler[0], $handler[1]);
            } elseif (\is_object($handler) && !$handler instanceof \Closure) {
                $reflection = new \ReflectionMethod($handler, '__invoke');
            } elseif ($handler instanceof \Closure) {
                $reflection = new \ReflectionFunction($handler);
            } elseif (\is_string($handler)) {
                $reflection = new \ReflectionFunction($handler);
            } else {
                return true;
            }
        } catch (\ReflectionException) {
            return true;
        }

        $parameters = $reflection->getParameters();
        if (empty($parameters)) {
            return true;
        }

        $firstParam = $parameters[0];
        $type = $firstParam->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return true;
        }

        $expectedClass = $type->getName();

        return $exception instanceof $expectedClass;
    }
}
