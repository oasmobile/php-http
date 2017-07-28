<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-25
 * Time: 10:30
 */

namespace Oasis\Mlib\Http\ServiceProviders\Twig;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\TwigConfiguration;
use Oasis\Mlib\Utils\DataProviderInterface;
use Pimple\Container;
use Silex\Api\BootableProviderInterface;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;

class SimpleTwigServiceProvider extends TwigServiceProvider
    implements BootableProviderInterface
{
    use ConfigurationValidationTrait;
    
    /** @var  Application\ */
    protected $kernel;
    
    public function __construct()
    {
    }
    
    public function boot(Application $app)
    {
        if ($app['twig.config.cache_dir']) {
            $app['twig.options'] = array_replace($app['twig.options'], ['cache' => $app['twig.config.cache_dir']]);
        }
        $app['twig.path'] = $app['twig.config.template_dir'];
    }
    
    public function register(Container $app)
    {
        $this->kernel = $app;
        parent::register($app);
        
        $app['twig'] = $app->extend(
            'twig',
            function ($twig, $c) {
                /** @var \Twig_Environment $twig */
                $twig->addGlobal('http', $c);
                
                foreach ($c['twig.config.global_vars'] as $k => $v) {
                    $twig->addGlobal($k, $v);
                }
                $twig->addFunction(
                    new \Twig_SimpleFunction(
                        'asset',
                        function ($assetFile, $version = '') use ($c) {
                            $url = $c['twig.config.asset_base'] . $assetFile;
                            if ($version !== '') {
                                $url .= "?v=$version";
                            }
                            
                            return $url;
                        }
                    )
                );
                
                return $twig;
            }
        );
        
        $app['twig.config.data_provider'] = function ($app) {
            return $this->processConfiguration($app['twig.config'], new TwigConfiguration());
        };
        $app['twig.config.template_dir']  = function () {
            return $this->getConfigDataProvider()->getMandatory('template_dir');
        };
        $app['twig.config.cache_dir']     = function () {
            return $this->getConfigDataProvider()->getOptional('cache_dir');
        };
        $app['twig.config.asset_base']    = function () {
            return $this->getConfigDataProvider()->getOptional('asset_base');
        };
        $app['twig.config.global_vars']   = function () {
            return $this->getConfigDataProvider()->getOptional(
                'globals',
                DataProviderInterface::ARRAY_TYPE,
                []
            );
        };
        
    }
    
    /**
     * @return DataProviderInterface
     */
    public function getConfigDataProvider()
    {
        if (!$this->kernel) {
            throw new \LogicException("Cannot get config data provider before registration");
        }
        
        return $this->kernel['twig.config.data_provider'];
    }
    
}
