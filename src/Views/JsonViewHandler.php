<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-02
 * Time: 10:36
 */

namespace Oasis\Mlib\Http\Views;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JsonViewHandler extends AbstractSmartViewHandler
{
    function __invoke($rawResult, Request $request)
    {
        if ($this->shouldHandle($request)) {
            return new JsonResponse($this->wrapResult($rawResult));
        }

        return null;
    }

    /**
     * This function will wrap the result from controller into the json object to be returned
     *
     * Any custom protocol should override this method to wrap the result in the desired format
     *
     * @param $rawResult
     *
     * @return array
     */
    protected function wrapResult($rawResult)
    {
        return is_scalar($rawResult) || is_null($rawResult) ? ["result" => $rawResult] : $rawResult;
    }

    /**
     * @return array
     */
    protected function getCompatibleTypes()
    {
        return ['application/json', 'text/json'];
    }
}
