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
    public function __invoke(mixed $rawResult, Request $request): ?JsonResponse
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
     * @param mixed $rawResult
     *
     * @return array<string, mixed>
     */
    protected function wrapResult(mixed $rawResult): array
    {
        return is_scalar($rawResult) || is_null($rawResult) ? ["result" => $rawResult] : $rawResult;
    }

    /**
     * @return array<string>
     */
    protected function getCompatibleTypes(): array
    {
        return ['application/json', 'text/json'];
    }
}
