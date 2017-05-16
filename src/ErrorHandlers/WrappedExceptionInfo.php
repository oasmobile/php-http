<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-06-14
 * Time: 11:52
 */

namespace Oasis\Mlib\Http\ErrorHandlers;

use Symfony\Component\HttpFoundation\Response;

class WrappedExceptionInfo implements \JsonSerializable
{
    /** @var  \Exception */
    protected $exception;
    /** @var  string */
    protected $shortExceptionType;
    /** @var  int */
    protected $code;
    /** @var  int */
    protected $originalCode;
    protected $attributes = [];
    
    public function __construct(\Exception $exception, $httpStatusCode)
    {
        $this->exception          = $exception;
        $this->shortExceptionType = (new \ReflectionClass($exception))->getShortName();
        $this->code               = $this->originalCode = $httpStatusCode;
        if ($this->code == 0) {
            $this->code = Response::HTTP_INTERNAL_SERVER_ERROR;
        }
    }
    
    public function __toArray($rich = false)
    {
        $ret = [
            'code'      => $this->getCode(),
            'exception' => $this->serializeException($this->getException()),
            'extra'     => $this->getAttributes(),
        ];
        if ($rich) {
            $ret['trace'] = $this->getException()->getTrace();
        }
        
        return $ret;
    }
    
    /**
     * Specify data which should be serialized to JSON
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *        which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return $this->__toArray();
    }
    
    public function getAttribute($key)
    {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
    }
    
    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
    
    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }
    
    /**
     * @param int $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }
    
    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }
    
    /**
     * @return int
     */
    public function getOriginalCode()
    {
        return $this->originalCode;
    }
    
    /**
     * @return string
     */
    public function getShortExceptionType()
    {
        return $this->shortExceptionType;
    }
    
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }
    
    protected function serializeException(\Exception $e)
    {
        $ret = [
            'type'    => (new \ReflectionClass($e))->getShortName(),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ];
        if ($e->getCode() != 0) {
            $ret['code'] = $e->getCode();
        }
        if ($e->getPrevious() instanceof \Exception) {
            $ret['previous'] = $this->serializeException($e->getPrevious());
        }
        
        return $ret;
    }
}
