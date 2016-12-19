<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-04-27
 * Time: 12:35
 */

namespace Oasis\Mlib\Http;

use Oasis\Mlib\Utils\AbstractDataProvider;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;

class ChainedParameterBagDataProvider extends AbstractDataProvider
{
    /** @var ParameterBag[]|HeaderBag[] */
    protected $bags;
    
    public function __construct(...$bags)
    {
        foreach ($bags as $bag) {
            if (!$bag instanceof ParameterBag
                && !$bag instanceof HeaderBag
            ) {
                throw new \InvalidArgumentException("Only ParameterBag|HeaderBag object can be chained.");
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
            
            if ($bag instanceof ParameterBag) {
                $value = $bag->get($key);
            }
            elseif ($bag instanceof HeaderBag) {
                // when header is presented only once, string value is returned, otherwise, array value is returned
                $value = $bag->get($key, null, false);
                if (is_array($value)) {
                    if (count($value) == 1) {
                        $value = $value[0];
                    }
                    elseif (count($value) == 0) {
                        $value = null;
                    }
                    else {
                        // $value = $value;
                    }
                }
            }
            else {
                throw new \LogicException("Bag type invalid!");
            }
            
            return $value;
        }
        
        return null;
    }
}
