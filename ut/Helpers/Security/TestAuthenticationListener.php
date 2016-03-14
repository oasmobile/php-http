<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 16:14
 */

namespace Oasis\Mlib\Http\Ut\Security;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class TestAuthenticationListener implements ListenerInterface
{
    protected $tokenStorage;
    protected $authenticationManager;
    protected $providerKey;

    public function __construct(TokenStorageInterface $tokenStorage,
                                AuthenticationManagerInterface $authenticationManager,
                                $providerKey
    )
    {
        $this->tokenStorage          = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey           = $providerKey;
    }
    
    /**
     * This interface must be implemented by firewall listeners.
     *
     * @param GetResponseEvent $event
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $username = $request->query->get('username', "NONE_EXIST");
        $password = $request->query->get('password');

        $token = $this->authenticationManager->authenticate(
            new UsernamePasswordToken($username, $password, $this->providerKey)
        );
        $this->tokenStorage->setToken($token);
    }
}
