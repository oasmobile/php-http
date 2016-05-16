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
use Silex\Application;
use Silex\Provider\TwigServiceProvider;

class SimpleTwigServiceProvider extends TwigServiceProvider
{
    use ConfigurationValidationTrait;

    /** @var  DataProviderInterface */
    protected $twigDataProvider;

    protected $templateDir;
    protected $cacheDir;
    protected $assetBase;

    protected $globalVariables = [];

    public function __construct(array $twigConfiguration)
    {
        $this->twigDataProvider = $this->processConfiguration($twigConfiguration, new TwigConfiguration());
        $this->templateDir      = $this->twigDataProvider->getMandatory('template_dir');
        $this->cacheDir         = $this->twigDataProvider->getOptional('cache_dir');
        $this->assetBase        = $this->twigDataProvider->getOptional('asset_base');
        $this->globalVariables  = $this->twigDataProvider->getOptional(
            'globals',
            DataProviderInterface::ARRAY_TYPE,
            []
        );
    }

    public function register(Application $app)
    {
        parent::register($app);

        $app['twig.path'] = $this->templateDir;
        if ($this->cacheDir) {
            $app['twig.options'] = array_replace($app['twig.options'], ['cache' => $this->cacheDir]);
        }
        $app['twig'] = $app->share(
            $app->extend(
                'twig',
                function ($twig, $c) {
                    /** @var \Twig_Environment $twig */
                    $twig->addGlobal('http', $c);

                    foreach ($this->globalVariables as $k => $v) {
                        $twig->addGlobal($k, $v);
                    }
                    $twig->addFunction(
                        new \Twig_SimpleFunction(
                            'asset',
                            function ($assetFile, $version = '') {
                                $url = $this->assetBase . $assetFile;
                                if ($version !== '') {
                                    $url .= "?v=$version";
                                }

                                return $url;
                            }
                        )
                    );

                    return $twig;
                }
            )
        );
    }

}
