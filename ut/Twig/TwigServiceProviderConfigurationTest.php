<?php

namespace Oasis\Mlib\Http\Test\Twig;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-25
 * Time: 11:53
 */
class TwigServiceProviderConfigurationTest extends TwigServiceProviderTest
{
    
    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        return require __DIR__ . "/app.twig2.php";
    }

}
