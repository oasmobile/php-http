<?php
use Symfony\Component\HttpKernel\HttpKernelInterface;

require_once "SecurityServiceProviderTest.php";

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 12:22
 */
class SecurityServiceProviderConfigurationTest extends SecurityServiceProviderTest
{
    
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $app = require __DIR__ . "/app.security2.php";
        
        $app['session.test'] = true;
        
        return $app;
    }
}
