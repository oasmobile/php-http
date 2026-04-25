<?php

namespace Oasis\Mlib\Http\ServiceProviders\Security;

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

interface AuthenticationPolicyInterface
{
    const AUTH_TYPE_LOGOUT      = "logout";
    const AUTH_TYPE_PRE_AUTH    = "pre_auth";
    const AUTH_TYPE_FORM        = "form";
    const AUTH_TYPE_HTTP        = "http";
    const AUTH_TYPE_REMEMBER_ME = "remember_me";
    const AUTH_TYPE_ANONYMOUS   = "anonymous";

    /**
     * 返回认证类型标识。
     */
    public function getAuthenticationType(): string;

    /**
     * 创建并返回 authenticator 实例。
     * 替代旧的 getAuthenticationProvider() + getAuthenticationListener()。
     *
     * @param array<string, mixed> $options
     */
    public function getAuthenticator(MicroKernel $kernel, string $firewallName, array $options): AuthenticatorInterface;

    /**
     * 返回 authenticator 的配置选项。
     *
     * @return array<string, mixed>
     */
    public function getAuthenticatorConfig(): array;

    /**
     * 返回认证入口点。
     *
     * @param array<string, mixed> $options
     */
    public function getEntryPoint(MicroKernel $kernel, string $name, array $options): AuthenticationEntryPointInterface;
}
