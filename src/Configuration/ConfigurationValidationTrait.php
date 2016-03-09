<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-07
 * Time: 15:13
 */

namespace Oasis\Mlib\Http\Configuration;

use Oasis\Mlib\Utils\ArrayDataProvider;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

trait ConfigurationValidationTrait
{
    public function processConfiguration(array $configArray, ConfigurationInterface $configurationInterface)
    {
        $processor    = new Processor();
        $processed    = $processor->processConfiguration($configurationInterface, [$configArray]);
        $dataProvider = new ArrayDataProvider($processed);

        return $dataProvider;
    }
}
