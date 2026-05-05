<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test\Helpers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Named function used as string callable error handler for testing shouldRunErrorHandler.
 */
function stringErrorHandler(\Throwable $e, Request $request, int $code): Response
{
    return new Response('string-handled', $code);
}
