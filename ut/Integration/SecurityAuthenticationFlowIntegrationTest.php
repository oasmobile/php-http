<?php
/**
 * Integration test for Security_Authentication_Flow (Requirement 10).
 *
 * Verifies the complete Policy → Firewall → AccessRule → Role Hierarchy chain
 * using a full HTTP request lifecycle via WebTestCase.
 */

namespace Oasis\Mlib\Http\Test\Integration;

use Oasis\Mlib\Http\Test\Helpers\RouteCacheCleaner;
use Silex\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SecurityAuthenticationFlowIntegrationTest extends WebTestCase
{
    use RouteCacheCleaner;

    /**
     * Creates the application.
     *
     * @return HttpKernelInterface
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/app.integration-security.php';

        $app['session.test'] = true;

        return $app;
    }

    protected function setUp()
    {
        $this->cleanRouteCache(__DIR__ . '/../cache');
        parent::setUp();
    }

    // ---------------------------------------------------------------
    // AC 1: Successful authentication + authorization
    // ---------------------------------------------------------------

    /**
     * Admin user (sig=abcd, ROLE_ADMIN) can access /integration/secured/admin → 200.
     */
    public function testAdminUserCanAccessAdminRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/admin', ['sig' => 'abcd']);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertContains('securedAdmin', $json['called']);
        $this->assertEquals('admin', $json['user']);
        $this->assertTrue($json['admin']);
    }

    /**
     * Admin user (sig=abcd, ROLE_ADMIN inherits ROLE_USER) can access /integration/secured/user → 200.
     */
    public function testAdminUserCanAccessUserRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/user', ['sig' => 'abcd']);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertContains('securedUser', $json['called']);
        $this->assertEquals('admin', $json['user']);
    }

    // ---------------------------------------------------------------
    // AC 2: Authentication failure → token null, AccessRule decides
    // ---------------------------------------------------------------

    /**
     * Request without sig → BadCredentialsException → NullEntryPoint → 403.
     */
    public function testNoSigResultsIn403()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/user');
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Request with invalid sig → UsernameNotFoundException → NullEntryPoint → 403.
     */
    public function testInvalidSigResultsIn403()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/user', ['sig' => 'invalid']);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // AC 3: AccessRule authorization fails → 403
    // ---------------------------------------------------------------

    /**
     * Child user (ROLE_CHILD, inherits ROLE_USER) accessing admin route (requires ROLE_ADMIN) → 403.
     */
    public function testChildUserCannotAccessAdminRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/admin', ['sig' => 'child']);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Child user (ROLE_CHILD, inherits ROLE_USER) accessing parent route (requires ROLE_PARENT) → 403.
     */
    public function testChildUserCannotAccessParentRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/parent', ['sig' => 'child']);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // AC 4: Role Hierarchy inheritance
    // ---------------------------------------------------------------

    /**
     * Parent user (ROLE_PARENT inherits ROLE_CHILD) can access child route → 200.
     */
    public function testParentUserCanAccessChildRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/child', ['sig' => 'parent']);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertContains('securedChild', $json['called']);
        $this->assertEquals('parent', $json['user']);
    }

    /**
     * Parent user (ROLE_PARENT inherits ROLE_USER) can access user route → 200.
     */
    public function testParentUserCanAccessUserRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/user', ['sig' => 'parent']);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertContains('securedUser', $json['called']);
        $this->assertEquals('parent', $json['user']);
    }

    /**
     * Child user (ROLE_CHILD inherits ROLE_USER) can access user route → 200.
     */
    public function testChildUserCanAccessUserRoute()
    {
        $client = $this->createClient();
        $client->request('GET', '/integration/secured/user', ['sig' => 'child']);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $json = json_decode($response->getContent(), true);
        $this->assertTrue(is_array($json));
        $this->assertContains('securedUser', $json['called']);
        $this->assertEquals('child', $json['user']);
    }
}
