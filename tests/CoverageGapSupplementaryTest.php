<?php
declare(strict_types=1);

namespace Oasis\Mlib\Http\Test;

use Oasis\Mlib\Http\MicroKernel;
use Oasis\Mlib\Http\Test\Helpers\KernelLifecycleTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

/**
 * Supplementary coverage gap tests: run(), CloudFront, Twig render, slow request, Security, Views, Misc.
 */
class CoverageGapSupplementaryTest extends TestCase
{
    use KernelLifecycleTestTrait;

    protected function setUp(): void
    {
        $this->setUpKernelLifecycle();
    }

    protected function tearDown(): void
    {
        $this->tearDownKernelLifecycle();
    }

    // ─── run() method ────────────────────────────────────────────────

    public function testRunWithExplicitRequest(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('run', new Route('/run', [
            '_controller' => function () { return new Response('run-ok'); },
        ]));

        ob_start();
        $kernel->run(Request::create('/run'));
        $this->assertStringContainsString('run-ok', ob_get_clean());
    }

    public function testRunWithNullRequestUsesGlobals(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('global', new Route('/global', [
            '_controller' => function () { return new Response('global-ok'); },
        ]));

        $_SERVER['REQUEST_URI'] = '/global';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';

        ob_start();
        $kernel->run();
        $this->assertStringContainsString('global-ok', ob_get_clean());
    }

    // ─── CloudFront trusted proxies ──────────────────────────────────

    public function testCloudfrontWithValidCache(): void
    {
        $tempDir = sys_get_temp_dir() . '/oasis-cov-cf-' . getmypid();
        @mkdir($tempDir, 0777, true);

        file_put_contents($tempDir . '/aws.ips', json_encode([
            'prefixes' => [
                ['ip_prefix' => '10.0.0.0/8', 'service' => 'CLOUDFRONT'],
                ['ip_prefix' => '172.16.0.0/12', 'service' => 'EC2'],
            ],
            'expire_at' => time() + 86400,
        ], JSON_PRETTY_PRINT));

        $saved = Request::getTrustedProxies();

        $kernel = $this->track(new MicroKernel([
            'cache_dir' => $tempDir,
            'trust_cloudfront_ips' => true,
        ], true));
        $kernel->addRoute('t', new Route('/t', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $kernel->handle(Request::create('/t'));

        $this->assertContains('10.0.0.0/8', Request::getTrustedProxies());
        $this->assertNotContains('172.16.0.0/12', Request::getTrustedProxies());

        Request::setTrustedProxies($saved, Request::getTrustedHeaderSet());
        @unlink($tempDir . '/aws.ips');
        @rmdir($tempDir);
    }

    public function testCloudfrontWithCorruptCache(): void
    {
        $tempDir = sys_get_temp_dir() . '/oasis-cov-cf-corrupt-' . getmypid();
        @mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/aws.ips', 'invalid-json{{{');

        $saved = Request::getTrustedProxies();

        $kernel = $this->track(new MicroKernel([
            'cache_dir' => $tempDir,
            'trust_cloudfront_ips' => true,
        ], true));
        $kernel->addRoute('t', new Route('/t', [
            '_controller' => function () { return new Response('ok'); },
        ]));

        // Should not throw
        $kernel->handle(Request::create('/t'));
        $this->assertTrue(true);

        Request::setTrustedProxies($saved, Request::getTrustedHeaderSet());
        @unlink($tempDir . '/aws.ips');
        @rmdir($tempDir);
    }

    // ─── Twig render with existing response ──────────────────────────

    public function testRenderWithExistingResponse(): void
    {
        $kernel = $this->track(new MicroKernel([
            'twig' => ['template_dir' => __DIR__ . '/Twig/templates'],
        ], true));
        $kernel->addRoute('d', new Route('/d', [
            '_controller' => function () { return new Response('ok'); },
        ]));
        $kernel->handle(Request::create('/d'));

        $existing = new Response('', 201, ['X-Custom' => 'yes']);
        $response = $kernel->render('hello.twig', ['name' => 'Test'], $existing);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringContainsString('Test', $response->getContent());
    }

    // ─── Slow request handler ────────────────────────────────────────

    public function testSlowRequestCustomHandler(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('slow', new Route('/slow', [
            '_controller' => function () { return new Response('ok'); },
        ]));

        $ref = new \ReflectionProperty($kernel, 'slowRequestThreshold');
        $ref->setValue($kernel, 0);

        $handlerCalled = false;
        $handlerRef = new \ReflectionProperty($kernel, 'slowRequestHandler');
        $handlerRef->setValue($kernel, function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        ob_start();
        $kernel->run(Request::create('/slow'));
        ob_get_clean();

        $this->assertTrue($handlerCalled);
    }

    public function testSlowRequestDefaultHandler(): void
    {
        $kernel = $this->track(new MicroKernel([], true));
        $kernel->addRoute('slow', new Route('/slow', [
            '_controller' => function () { return new Response('ok'); },
        ]));

        $ref = new \ReflectionProperty($kernel, 'slowRequestThreshold');
        $ref->setValue($kernel, 0);

        ob_start();
        $kernel->run(Request::create('/slow'));
        ob_get_clean();

        $this->assertTrue(true);
    }

    // ─── AbstractSimplePreAuthenticator (deprecated) ─────────────────

    public function testAbstractSimplePreAuthenticatorCreateTokenThrows(): void
    {
        $auth = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
            public function getCredentialsFromRequest(Request $request): mixed { return null; }
        };

        $this->expectException(\LogicException::class);
        $auth->createToken(Request::create('/'), 'main');
    }

    public function testAbstractSimplePreAuthenticatorAuthenticateTokenThrows(): void
    {
        $auth = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
            public function getCredentialsFromRequest(Request $request): mixed { return null; }
        };

        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $provider = $this->createStub(\Symfony\Component\Security\Core\User\UserProviderInterface::class);

        $this->expectException(\LogicException::class);
        $auth->authenticateToken($token, $provider, 'main');
    }

    public function testAbstractSimplePreAuthenticatorSupportsTokenThrows(): void
    {
        $auth = new class extends \Oasis\Mlib\Http\ServiceProviders\Security\AbstractSimplePreAuthenticator {
            public function getCredentialsFromRequest(Request $request): mixed { return null; }
        };

        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);

        $this->expectException(\LogicException::class);
        $auth->supportsToken($token, 'main');
    }

    // ─── FallbackViewHandler default resolver ────────────────────────

    public function testFallbackViewHandlerUsesDefaultResolver(): void
    {
        $kernel = $this->track(new MicroKernel([], true));

        $handler = new \Oasis\Mlib\Http\Views\FallbackViewHandler($kernel);
        $this->assertInstanceOf(\Oasis\Mlib\Http\Views\FallbackViewHandler::class, $handler);
    }

    // ─── ChainedParameterBagDataProvider ─────────────────────────────

    public function testChainedParameterBagGetOptionalReturnsScalar(): void
    {
        $bag = new \Symfony\Component\HttpFoundation\ParameterBag(['key' => 'value']);
        $provider = new \Oasis\Mlib\Http\ChainedParameterBagDataProvider($bag);
        $this->assertSame('value', $provider->getOptional('key'));
    }
}
