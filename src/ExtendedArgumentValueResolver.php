<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 25/07/2017
 * Time: 4:44 PM
 */

namespace Oasis\Mlib\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ExtendedArgumentValueResolver implements ValueResolverInterface
{
    protected $mappingParameters = [];
    
    public function __construct($autoParameters)
    {
        foreach ($autoParameters as $parameter) {
            if (!is_object($parameter)) {
                throw new \InvalidArgumentException("Auto parameter should be an object.");
            }
            $this->mappingParameters[get_class($parameter)] = $parameter;
        }
    }
    
    /**
     * Returns the possible value(s).
     *
     * In Symfony 7.x, supports() was removed from the interface.
     * Return an empty array when the argument is not supported.
     *
     * @param Request          $request
     * @param ArgumentMetadata $argument
     *
     * @return iterable
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $classname = $argument->getType();
        if (!$classname || !\class_exists($classname)) {
            return [];
        }
        if (\array_key_exists($classname, $this->mappingParameters)) {
            return [$this->mappingParameters[$classname]];
        }
        foreach ($this->mappingParameters as $value) {
            if ($value instanceof $classname) {
                return [$value];
            }
        }
        
        return [];
    }
}
