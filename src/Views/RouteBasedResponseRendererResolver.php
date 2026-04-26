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
    public function resolveRequest(Request $request): ResponseRendererInterface
    {
        $format = $request->attributes->get(
            'format',
            $request->attributes->get('_format', 'html')
        );
        
        return match ($format) {
            'html', 'page' => new DefaultHtmlRenderer(),
            'api', 'json' => new JsonApiRenderer(),
            default => throw new InvalidConfigurationException(
                sprintf("Unsupported response format %s", $format)
            ),
        };
    }
}
