<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-03
 * Time: 21:11
 */

namespace Oasis\Mlib\Http\ErrorHandlers;

/**
 * Class JsonErrorHandler
 *
 * This class returns a json style object, i.e. key=>value array
 *
 * Further view handler should take care of this "result" and format it into proper view
 *
 * @package Oasis\Mlib\Http\ErrorHandlers
 */
class JsonErrorHandler
{
    function __invoke(\Exception $e, $code)
    {
        mtrace($e, "Exception while processing request, code = $code.");

        return
            [
                "code"    => $code,
                "type"    => get_class($e),
                "message" => $e->getMessage(),
                "file"    => $e->getFile(),
                "line"    => $e->getLine(),
            ];
    }
}
