<?php
/**
 * Created by PhpStorm.
 * User: xuchang
 * Date: 2019/6/27
 * Time: 17:15
 */

namespace Oasis\Mlib\Http\Test\Security;

use Pimple\Container;
use Symfony\Component\HttpKernel\EventListener\AbstractTestSessionListener;

class TestSessionListener extends AbstractTestSessionListener
{

    private $app;

    public function __construct(Container $app)
    {

        $this->app = $app;
        parent::__construct([]);
    }

    protected function getSession()
    {

        if (!isset($this->app['session'])) {
            return null;
        }

        return $this->app['session'];
    }
}
