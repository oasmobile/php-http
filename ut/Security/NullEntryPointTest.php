<?php

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\ServiceProviders\Security\NullEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class NullEntryPointTest extends TestCase
{
    public function testStartWithAuthenticationExceptionThrowsAccessDeniedWithMessage()
    {
        $entryPoint = new NullEntryPoint();
        $request = Request::create('/');
        $authException = new AuthenticationException('Custom auth error');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Custom auth error');

        $entryPoint->start($request, $authException);
    }

    public function testStartWithoutExceptionThrowsAccessDeniedWithDefaultMessage()
    {
        $entryPoint = new NullEntryPoint();
        $request = Request::create('/');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Access Denied');

        $entryPoint->start($request);
    }
}
