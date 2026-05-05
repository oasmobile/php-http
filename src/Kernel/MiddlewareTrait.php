<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Kernel;

use Oasis\Mlib\Http\EventSubscribers\ViewHandlerSubscriber;
use Oasis\Mlib\Http\Middlewares\CallbackMiddleware;
use Oasis\Mlib\Http\Middlewares\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Middleware and event handler registration extracted from MicroKernel.
 */
trait MiddlewareTrait
{
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function before(callable $callback, int $priority = 0, bool $masterRequestOnly = true): void
    {
        $this->addMiddleware(new CallbackMiddleware(
            beforeCallback: $callback,
            afterCallback: null,
            beforePriority: $priority,
            afterPriority: false,
            masterRequestOnly: $masterRequestOnly,
            kernel: $this,
        ));
    }

    public function after(callable $callback, int $priority = 0, bool $masterRequestOnly = true): void
    {
        $this->addMiddleware(new CallbackMiddleware(
            beforeCallback: null,
            afterCallback: $callback,
            afterPriority: $priority,
            beforePriority: false,
            masterRequestOnly: $masterRequestOnly,
            kernel: $this,
        ));
    }

    public function error(callable $callback, int $priority = -8): void
    {
        if ($this->booted) {
            $this->registerSingleErrorHandler($callback, $priority);
        } else {
            $this->errorHandlers[] = $callback;
        }
    }

    public function view(callable $callback): void
    {
        $this->viewHandlers[] = $callback;
    }

    protected function registerMiddlewares(): void
    {
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
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

    protected function registerViewHandlers(): void
    {
        if (empty($this->viewHandlers)) {
            return;
        }

        $subscriber = new ViewHandlerSubscriber($this->viewHandlers);
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $dispatcher->addSubscriber($subscriber);
    }
}
