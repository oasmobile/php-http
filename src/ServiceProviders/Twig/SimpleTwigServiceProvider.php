<?php

namespace Oasis\Mlib\Http\ServiceProviders\Twig;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\TwigConfiguration;
use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Utils\DataProviderInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Twig service provider — creates and configures a \Twig\Environment instance
 * from Bootstrap_Config's `twig` key.
 *
 * Replaces the former Silex\Provider\TwigServiceProvider inheritance.
 */
class SimpleTwigServiceProvider
{
    use ConfigurationValidationTrait;

    /**
     * Register Twig services on the given MicroKernel.
     *
     * Reads the `twig` key from Bootstrap_Config, creates a \Twig\Environment,
     * registers globals and the `asset()` function, and stores the instance
     * on the kernel via setTwigEnvironment().
     *
     * @param MicroKernel $kernel
     * @param array       $twigConfig The raw `twig` config array from Bootstrap_Config
     */
    public function register(MicroKernel $kernel, array $twigConfig): void
    {
        $dataProvider = $this->processConfiguration($twigConfig, new TwigConfiguration());

        $templateDir     = $dataProvider->getMandatory('template_dir');
        $cacheDir        = $dataProvider->getOptional('cache_dir');
        $assetBase       = $dataProvider->getOptional('asset_base', DataProviderInterface::STRING_TYPE, '');
        $globals         = $dataProvider->getOptional('globals', DataProviderInterface::ARRAY_TYPE, []);
        $strictVariables = $dataProvider->getOptional('strict_variables', DataProviderInterface::BOOL_TYPE, true);
        $autoReload      = $dataProvider->getOptional('auto_reload', DataProviderInterface::MIXED_TYPE);

        // Build Twig options
        $options = [];
        if ($cacheDir) {
            $options['cache'] = $cacheDir;
        }
        $options['strict_variables'] = $strictVariables;

        // auto_reload: null → auto-detect via $kernel->isDebug(); otherwise use explicit value
        if ($autoReload === null) {
            $options['auto_reload'] = $kernel->isDebug();
        } else {
            $options['auto_reload'] = (bool) $autoReload;
        }

        // Create Twig environment
        $loader = new FilesystemLoader($templateDir);
        $twig   = new TwigEnvironment($loader, $options);

        // Register default 'http' global (the kernel itself, matching old SilexKernel behavior)
        $twig->addGlobal('http', $kernel);

        // Register user-defined globals
        foreach ($globals as $key => $value) {
            $twig->addGlobal($key, $value);
        }

        // Register asset() function
        $twig->addFunction(
            new TwigFunction(
                'asset',
                function (string $assetFile, string $version = '') use ($assetBase): string {
                    $url = $assetBase . $assetFile;
                    if ($version !== '') {
                        $url .= "?v=$version";
                    }

                    return $url;
                }
            )
        );

        // Register is_granted() function for template compatibility
        $twig->addFunction(
            new TwigFunction(
                'is_granted',
                function ($role) use ($kernel): bool {
                    return $kernel->isGranted($role);
                }
            )
        );

        // Store on kernel
        $kernel->setTwigEnvironment($twig);
    }
}
