<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-04-27
 * Time: 12:35
 */

namespace Oasis\Mlib\Http;

use Oasis\Mlib\Utils\AbstractDataProvider;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ChainedParameterBagDataProvider extends AbstractDataProvider
{
    /** @var ParameterBag[] */
    protected $bags;

    public function __construct(...$bags)
    {
        foreach ($bags as $bag) {
            if (!$bag instanceof ParameterBag) {
                throw new \InvalidArgumentException("Only ParameterBag object can be chained.");
            }
        }
        $this->bags = $bags;
    }

    /**
     * @param string $key the key to be used to read a value from the data provider
     *
     * @return mixed|null       null indicates the value is not presented in the data provider
     */
    protected function getValue($key)
    {
        foreach ($this->bags as $bag) {
            if (!$bag->has($key)) {
                continue;
            }

            return $bag->get($key);
        }
    }
}
