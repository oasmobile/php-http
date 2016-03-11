<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-11
 * Time: 18:11
 */

namespace Oasis\Mlib\Http\ServiceProviders;

use Silex\Application;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class SimpleAuthenticationPolicy implements AuthenticationPolicyInterface
{

    protected $allowAnonymous = false;

    /** @var  callable */
    protected $listnerFactory;
    /** @var  callable */
    protected $entryPointFactory;
    protected $authenticationType;

    /**
     * @return boolean
     */
    public function isAnonymousAllowed()
    {
        return $this->allowAnonymous;
    }

    /**
     * @param boolean $allowAnonymous
     */
    public function setAnonymousAllowed($allowAnonymous)
    {
        $this->allowAnonymous = $allowAnonymous;
    }

    /**
     * @return callable
     */
    public function getListnerFactory()
    {
        return $this->listnerFactory;
    }

    /**
     * @param callable $listnerFactory
     */
    public function setListnerFactory($listnerFactory)
    {
        $this->listnerFactory = $listnerFactory;
    }

    /**
     * @return callable
     */
    public function getEntryPointFactory()
    {
        return $this->entryPointFactory;
    }

    /**
     * @param callable $entryPointFactory
     */
    public function setEntryPointFactory($entryPointFactory)
    {
        $this->entryPointFactory = $entryPointFactory;
    }

    public function getAuthenticationType()
    {
        return $this->authenticationType;
    }

    /**
     * @param mixed $authenticationType
     */
    public function setAuthenticationType($authenticationType)
    {
        $this->authenticationType = $authenticationType;
    }

    /**
     * @param Application $app
     * @param             $name
     * @param             $options
     *
     * @return ListenerInterface
     */
    public function getAuthenticationListener(Application $app, $name, $options)
    {
        return call_user_func($this->listnerFactory, $app, $name, $options);
    }

    /**
     * @param Application $app
     * @param             $name
     * @param             $options
     *
     * @return AuthenticationEntryPointInterface
     */
    public function getEntryPoint(Application $app, $name, $options)
    {
        return call_user_func($this->entryPointFactory, $app, $name, $options);
    }
}
