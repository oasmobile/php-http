<?php

namespace Oasis\Mlib\Http\Test\Configuration;

use Oasis\Mlib\Http\Configuration\ConfigurationValidationTrait;
use Oasis\Mlib\Http\Configuration\HttpConfiguration;
use Oasis\Mlib\Utils\ArrayDataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Concrete class that uses ConfigurationValidationTrait for testing.
 */
class ConfigurationValidationTraitConsumer
{
    use ConfigurationValidationTrait;
}

class ConfigurationValidationTraitTest extends TestCase
{
    /** @var ConfigurationValidationTraitConsumer */
    private $consumer;

    protected function setUp()
    {
        $this->consumer = new ConfigurationValidationTraitConsumer();
    }

    //----------------------------------------------------------------------
    // Returns ArrayDataProvider
    //----------------------------------------------------------------------

    public function testProcessConfigurationReturnsArrayDataProvider()
    {
        $result = $this->consumer->processConfiguration(
            [],
            new HttpConfiguration()
        );

        $this->assertInstanceOf(ArrayDataProvider::class, $result);
    }

    //----------------------------------------------------------------------
    // Valid configuration processing
    //----------------------------------------------------------------------

    public function testValidConfigurationProcessedSuccessfully()
    {
        $config = [
            'cache_dir'  => '/tmp/cache',
            'behind_elb' => true,
        ];

        $result = $this->consumer->processConfiguration(
            $config,
            new HttpConfiguration()
        );

        $this->assertInstanceOf(ArrayDataProvider::class, $result);
    }

    //----------------------------------------------------------------------
    // Invalid configuration throws exception
    //----------------------------------------------------------------------

    public function testInvalidConfigurationThrowsException()
    {
        $this->setExpectedException(\Exception::class);

        $config = [
            'unknown_key' => 'should_fail',
        ];

        $this->consumer->processConfiguration(
            $config,
            new HttpConfiguration()
        );
    }
}
