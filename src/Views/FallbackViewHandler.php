<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 16:17
 */

namespace Oasis\Mlib\Http\Views;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Request;

class FallbackViewHandler
{
    /**
     * @var MicroKernel
     */
    protected $kernel;
    /**
     * @var ResponseRendererResolverInterface
     */
    protected $rendererResolver;
    
    /**
     * FallbackViewHandler constructor.
     *
     * @param MicroKernel                       $kernel
     * @param ResponseRendererResolverInterface $rendererResolver
     */
    public function __construct(MicroKernel $kernel, $rendererResolver = null)
    {
        if ($rendererResolver == null) {
            $rendererResolver = new RouteBasedResponseRendererResolver();
        }
        $this->kernel           = $kernel;
        $this->rendererResolver = $rendererResolver;
    }
    
    public function __invoke($result, Request $request)
    {
        $renderer = $this->rendererResolver->resolveRequest($request);
        if ($result instanceof WrappedExceptionInfo) {
            $response = $renderer->renderOnException($result, $this->kernel);
        }
        else {
            $response = $renderer->renderOnSuccess($result, $this->kernel);
        }
        
        return $response;
    }
    
}
