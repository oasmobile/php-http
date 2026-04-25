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
use Symfony\Component\HttpFoundation\Response;

class FallbackViewHandler
{
    protected ResponseRendererResolverInterface $rendererResolver;
    
    public function __construct(
        protected readonly MicroKernel $kernel,
        ?ResponseRendererResolverInterface $rendererResolver = null
    ) {
        if ($rendererResolver === null) {
            $rendererResolver = new RouteBasedResponseRendererResolver();
        }
        $this->rendererResolver = $rendererResolver;
    }
    
    public function __invoke(mixed $result, Request $request): Response
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
