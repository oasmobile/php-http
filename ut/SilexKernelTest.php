<?php
use Oasis\Mlib\Http\SilexKernel;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-08
 * Time: 11:01
 */
class SilexKernelTest extends PHPUnit_Framework_TestCase
{
    public function testCreationWithOkConfig()
    {
        require __DIR__ . '/app.php';
    }

    public function testCreationWithWrongConfiguration()
    {
        $config = [
            'routing2' => [
                'path'       => __DIR__ . "/routes.yml",
                'namespaces' => [
                    'Oasis\\Mlib\\Http\\Test',
                ],
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);

        new SilexKernel($config, true);
    }
}
