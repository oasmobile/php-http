<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 16:17
 */

namespace Oasis\Mlib\Http\Views;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\SilexKernel;
use Symfony\Component\HttpFoundation\Request;

class FallbackViewHandler
{
    /**
     * @var SilexKernel
     */
    protected $silexKernel;
    /**
     * @var ResponseRendererResolverInterface
     */
    protected $rendererResolver;
    
    public function __construct(SilexKernel $silexKernel, ResponseRendererResolverInterface $rendererResolver)
    {
        $this->silexKernel      = $silexKernel;
        $this->rendererResolver = $rendererResolver;
    }
    
    public function __invoke($result, Request $request)
    {
        $renderer = $this->rendererResolver->resolveRequest($request);
        if ($result instanceof WrappedExceptionInfo) {
            $response = $renderer->renderOnException($result, $this->silexKernel);
        }
        else {
            $response = $renderer->renderOnSuccess($result, $this->silexKernel);
        }
        
        return $response;
    }
    
}
