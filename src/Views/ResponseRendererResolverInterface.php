<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2017-01-17
 * Time: 16:41
 */

namespace Oasis\Mlib\Http\Views;

use Symfony\Component\HttpFoundation\Request;

interface ResponseRendererResolverInterface
{
    /**
     * @param Request $request
     *
     * @return ResponseRendererInterface
     */
    public function resolveRequest(Request $request);
}
