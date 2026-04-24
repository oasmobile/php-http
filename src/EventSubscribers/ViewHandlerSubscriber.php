<?php

namespace Oasis\Mlib\Http\EventSubscribers;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens on KernelEvents::VIEW and iterates through registered view handlers.
 * When a handler returns a Response, the chain stops and that Response is set on the event.
 */
class ViewHandlerSubscriber implements EventSubscriberInterface
{
    /** @var callable[] */
    private array $handlers;

    /**
     * @param callable[] $handlers
     */
    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::VIEW => ['onView', 0]];
    }

    public function onView(ViewEvent $event): void
    {
        $result  = $event->getControllerResult();
        $request = $event->getRequest();

        foreach ($this->handlers as $handler) {
            $response = $handler($result, $request);
            if ($response instanceof Response) {
                $event->setResponse($response);

                return;
            }
        }
    }
}
