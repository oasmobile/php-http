<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-03
 * Time: 21:11
 */

namespace Oasis\Mlib\Http\Views;

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonErrorViewHandler
{
    function __invoke(\Exception $e, $code)
    {
        mtrace($e, "Exception while processing request, code = $code.");

        return new JsonResponse(
            [
                "code"    => $code,
                "type"    => get_class($e),
                "message" => $e->getMessage(),
                "file"    => $e->getFile(),
                "line"    => $e->getLine(),
            ]
        );
    }
}
