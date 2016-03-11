<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-11
 * Time: 16:47
 */

namespace Oasis\Mlib\Http\Ut;


use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class TestPreAuthAuthenticationListener implements ListenerInterface
{
    /**
     * This interface must be implemented by firewall listeners.
     *
     * @param GetResponseEvent $event
     */
    public function handle(GetResponseEvent $event)
    {
        mdebug("test-pre-auth listener called");
        // TODO: Implement handle() method.
    }
}
