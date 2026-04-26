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
    protected \Exception $exception;
    protected string $shortExceptionType;
    protected int $code;
    protected int $originalCode;
    /** @var array<string, mixed> */
    protected array $attributes = [];

    
    public function __construct(\Exception $exception, int $httpStatusCode)
    {
        $this->exception          = $exception;
        $this->shortExceptionType = (new \ReflectionClass($exception))->getShortName();
        $this->code               = $this->originalCode = $httpStatusCode;
        if ($this->code === 0) {
            $this->code = Response::HTTP_INTERNAL_SERVER_ERROR;
        }
    }
    
    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $rich = false): array
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
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
    
    public function getAttribute(string $key): mixed
    {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
    }
    
    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
    
    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }
    
    /**
     * @param int $code
     */
    public function setCode(int $code): void
    {
        $this->code = $code;
    }
    
    /**
     * @return \Exception
     */
    public function getException(): \Exception
    {
        return $this->exception;
    }
    
    /**
     * @return int
     */
    public function getOriginalCode(): int
    {
        return $this->originalCode;
    }
    
    /**
     * @return string
     */
    public function getShortExceptionType(): string
    {
        return $this->shortExceptionType;
    }
    
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }
    
    /**
     * @return array<string, mixed>
     */
    protected function serializeException(\Exception $e): array
    {
        $ret = [
            'type'    => (new \ReflectionClass($e))->getShortName(),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ];
        if ($e->getCode() !== 0) {
            $ret['code'] = $e->getCode();
        }
        if ($e->getPrevious() instanceof \Exception) {
            $ret['previous'] = $this->serializeException($e->getPrevious());
        }
        
        return $ret;
    }
}
