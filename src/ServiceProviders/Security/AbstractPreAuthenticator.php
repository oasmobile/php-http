<?php

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Template-method base class for pre-authentication authenticators.
 *
 * Implements Symfony 7.x AuthenticatorInterface and reduces the subclass
 * contract to two methods:
 *  - getCredentialsFromRequest(): extract credentials from the request
 *  - authenticateAndGetUser():    look up and return the authenticated user
 */
abstract class AbstractPreAuthenticator implements AuthenticatorInterface
{
    /**
     * 从 Request 中提取凭证。返回 null 表示该 authenticator 不支持此请求。
     *
     * @return mixed|null 凭证数据，null 表示无凭证
     */
    abstract protected function getCredentialsFromRequest(Request $request): mixed;

    /**
     * 根据凭证查找并返回已认证的用户。
     *
     * @param mixed $credentials getCredentialsFromRequest() 返回的凭证
     * @throws AuthenticationException 认证失败时抛出
     */
    abstract protected function authenticateAndGetUser(mixed $credentials): UserInterface;

    public function supports(Request $request): ?bool
    {
        return $this->getCredentialsFromRequest($request) !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $credentials = $this->getCredentialsFromRequest($request);
        $user = $this->authenticateAndGetUser($credentials);

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn() => $user)
        );
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();

        return new PostAuthenticationToken($user, $firewallName, $user->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // 不中断请求处理
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null; // 不阻断请求
    }
}
