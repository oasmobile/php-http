<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 25/07/2017
 * Time: 4:44 PM
 */

namespace Oasis\Mlib\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ExtendedArgumentValueResolver implements ArgumentValueResolverInterface
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
     * Whether this resolver can resolve the value for the given ArgumentMetadata.
     *
     * @param Request          $request
     * @param ArgumentMetadata $argument
     *
     * @return bool
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        $classname = $argument->getType();
        if (!\class_exists($classname)) {
            return false;
        }
        if (\array_key_exists($classname, $this->mappingParameters)) {
            return true;
        }
        else {
            foreach ($this->mappingParameters as $value) {
                if ($value instanceof $classname) {
                    return true;
                }
            }
            
            return false;
        }
    }
    
    /**
     * Returns the possible value(s).
     *
     * @param Request          $request
     * @param ArgumentMetadata $argument
     *
     * @return \Generator
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        if (\array_key_exists($argument->getType(), $this->mappingParameters)) {
            yield $this->mappingParameters[$argument->getType()];
        }
        else {
            foreach ($this->mappingParameters as $value) {
                $classname = $argument->getType();
                if ($value instanceof $classname) {
                    yield $value;
                }
            }
        }
    }
}
