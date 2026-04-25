<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-07
 * Time: 17:33
 */

namespace Oasis\Mlib\Http\Views;

use Symfony\Component\HttpFoundation\Response;

class PrefilightResponse extends Response
{
    /** @var array<string> */
    protected array $allowedMethods = [];
    
    protected bool $frozen = false;
    
    public function __construct()
    {
        parent::__construct('', static::HTTP_NO_CONTENT, ['X-Status-Code' => static::HTTP_NO_CONTENT]);
    }
    
    /**
     * @return array<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
    
    public function addAllowedMethod(string $method): void
    {
        $this->allowedMethods[] = $method;
    }
    
    public function isFrozen(): bool
    {
        return $this->frozen;
    }
    
    public function freeze(): void
    {
        $this->frozen = true;
    }
    
}
