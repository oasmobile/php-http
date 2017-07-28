<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-14
 * Time: 21:21
 */

namespace Oasis\Mlib\Http\Test\Helpers\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class TestApiUserPreAuthenticator extends AbstractSimplePreAuthenticator
{
    public function getCredentialsFromRequest(Request $request)
    {
        $apiKey = $request->query->get('sig');

        if (!$apiKey) {
            throw new BadCredentialsException("sig not found!");
        }

        return $apiKey;
    }
}
