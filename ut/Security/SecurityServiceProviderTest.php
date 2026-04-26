<?php

namespace Oasis\Mlib\Http\Test\Security;

use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Oasis\Mlib\Http\Test\Helpers\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 12:22
 */
class SecurityServiceProviderTest extends WebTestCase
{
    use RouteCacheCleaner;

    protected function setUp(): void
    {
        $this->cleanRouteCache(__DIR__ . '/../cache');
        parent::setUp();
    }

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $cacheDir = static::createTempCacheDir();
        $app = require __DIR__ . "/app.security.php";
        
        // Note: session.test configuration is not available in MicroKernel
        // Security tests are expected to fail in Phase 1 (except NullEntryPointTest)
        
        return $app;
    }
    
    public function testPreAuth()
    {
        //$this->markTestSkipped();
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin'
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        $client->request(
            'GET',
            '/secured/madmin',
            [
                'sig' => 'xyz', // false apiKey
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
        $client->request(
            'GET',
            '/secured/madmin',
            [
                'sig' => 'abcd',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\AuthController::madmin()', $json['called']);
        $this->assertEquals(true, $json['admin']);
    }
    
    public function testAccessRuleOk()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/parent',
            [
                'sig' => 'parent',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\AuthController::madminParent()', $json['called']);
        $this->assertEquals('parent', $json['user']);
        
    }
    
    public function testAccessRuleOnHostWithRole()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/parent',
            [
                'sig' => 'child',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
    }
    
    public function testAccessRuleOnHostNoRole()
    {
        $client = $this->createClient(['HTTP_HOST' => "baida.com"]);
        $client->request(
            'GET',
            '/secured/madmin/parent',
            [
                'sig' => 'child',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        
    }
    
    public function testAccessRuleWithRoleHierarchy()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/child',
            [
                'sig' => 'parent',
            ]
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals('Oasis\\Mlib\\Http\\Test\\Helpers\\Controllers\\AuthController::madminChild()', $json['called']);
        $this->assertEquals('parent', $json['user']);
    }
    
    // --- Supplementary tests for R12 AC 3 ---
    
    /**
     * Authentication failure: no credentials provided at all (no sig parameter).
     * Pre-auth listener throws BadCredentialsException → token is null → AccessRule denies.
     */
    public function testPreAuthNoCredentials()
    {
        $client = $this->createClient();
        $client->request('GET', '/secured/madmin');
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
    
    /**
     * Authentication failure: invalid credentials (unknown sig).
     * UserProvider throws UsernameNotFoundException → token is null → AccessRule denies.
     */
    public function testPreAuthInvalidCredentials()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin',
            ['sig' => 'nonexistent_key']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
    
    /**
     * AccessRule boundary: ROLE_ADMIN required, user has ROLE_ADMIN → 200.
     */
    public function testAccessRuleAdminWithAdminRole()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/admin',
            ['sig' => 'abcd']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
    
    /**
     * AccessRule boundary: ROLE_ADMIN required, user has only ROLE_PARENT (no ROLE_ADMIN) → 403.
     */
    public function testAccessRuleAdminWithoutAdminRole()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/admin',
            ['sig' => 'parent']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
    
    /**
     * AccessRule boundary: ROLE_ADMIN required, user has only ROLE_CHILD (no ROLE_ADMIN) → 403.
     */
    public function testAccessRuleAdminWithChildRole()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/admin',
            ['sig' => 'child']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
    
    /**
     * Role Hierarchy multi-level: ROLE_GOOD inherits ROLE_USER.
     * User 'abcd' has ROLE_GOOD and ROLE_ADMIN.
     * Accessing /secured/madmin (requires ROLE_USER) should succeed via hierarchy.
     */
    public function testRoleHierarchyGoodInheritsUser()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin/good',
            ['sig' => 'abcd']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertEquals(true, $json['right']);
    }
    
    /**
     * Role Hierarchy multi-level: ROLE_PARENT inherits ROLE_CHILD inherits ROLE_USER.
     * User 'parent' has ROLE_PARENT → should have ROLE_CHILD and ROLE_USER via hierarchy.
     * Accessing /secured/madmin (requires ROLE_USER) should succeed.
     */
    public function testRoleHierarchyMultiLevel()
    {
        $client = $this->createClient();
        $client->request(
            'GET',
            '/secured/madmin',
            ['sig' => 'parent']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
    
    /**
     * Role Hierarchy: ROLE_CHILD inherits ROLE_USER but NOT ROLE_PARENT.
     * User 'child' has ROLE_CHILD → should NOT have ROLE_PARENT.
     * Accessing /secured/madmin/parent on matching host (requires ROLE_PARENT) → 403.
     */
    public function testRoleHierarchyChildDoesNotInheritParent()
    {
        $client = $this->createClient(['HTTP_HOST' => 'baidu.com']);
        $client->request(
            'GET',
            '/secured/madmin/parent',
            ['sig' => 'child']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
    
    /**
     * AccessRule with RequestMatcher host pattern: matching host "baida.com" with ROLE_PARENT → 200.
     */
    public function testAccessRuleHostPatternMatchBaida()
    {
        $client = $this->createClient(['HTTP_HOST' => 'baida.com']);
        $client->request(
            'GET',
            '/secured/madmin/parent',
            ['sig' => 'parent']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
    
    /**
     * AccessRule with RequestMatcher host pattern: non-matching host falls through to
     * the generic /secured/madmin rule (requires ROLE_USER). User 'parent' has ROLE_USER
     * via hierarchy → 200.
     */
    public function testAccessRuleHostPatternNoMatchFallsThrough()
    {
        $client = $this->createClient(['HTTP_HOST' => 'google.com']);
        $client->request(
            'GET',
            '/secured/madmin/parent',
            ['sig' => 'parent']
        );
        $response = $client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
    
}
