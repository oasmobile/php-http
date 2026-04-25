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
    protected array $mappingParameters = [];
    
    public function __construct(array $autoParameters)
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
     * Symfony 7.x 用 ValueResolverInterface 替代了 ArgumentValueResolverInterface，
     * 移除了 supports() 方法。原来的两步流程（supports() 判断 + resolve() 取值）
     * 合并为一步：resolve() 返回空数组表示不支持，返回非空数组表示匹配成功。
     *
     * 匹配逻辑与原 supports() 一致：精确类名匹配优先，其次 instanceof 匹配。
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
