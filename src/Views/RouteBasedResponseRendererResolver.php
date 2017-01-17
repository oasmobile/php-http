<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 17:29
 */

namespace Oasis\Mlib\Http\Views;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;

class RouteBasedResponseRendererResolver implements ResponseRendererResolverInterface
{
    /**
     * @param Request $request
     *
     * @return ResponseRendererInterface
     */
    public function resolveRequest(Request $request)
    {
        $format = $request->attributes->get(
            'format',
            $request->attributes->get('_format', 'html')
        );
        
        switch ($format) {
            case 'html':
            case 'page':
                return new DefaultHtmlRenderer();
                break;
            case 'api':
            case 'json':
                return new JsonApiRenderer();
                break;
            default:
                throw new InvalidConfigurationException(sprintf("Unsupported response format %s", $format));
        }
    }
}
