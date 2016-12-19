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
    protected $allowedMethods = [];
    
    protected $frozen = false;
    
    public function __construct()
    {
        parent::__construct('', static::HTTP_NO_CONTENT, ['X-Status-Code' => static::HTTP_NO_CONTENT]);
    }
    
    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return $this->allowedMethods;
    }
    
    public function addAllowedMethod($method)
    {
        $this->allowedMethods[] = $method;
    }
    
    /**
     * @return boolean
     */
    public function isFrozen()
    {
        return $this->frozen;
    }
    
    public function freeze()
    {
        $this->frozen = true;
    }
    
}
