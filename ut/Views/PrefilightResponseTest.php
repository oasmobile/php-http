<?php

namespace Oasis\Mlib\Http\Test\Views;

use Oasis\Mlib\Http\Views\PrefilightResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class PrefilightResponseTest extends TestCase
{
    //----------------------------------------------------------------------
    // Construction — 204 status + X-Status-Code header
    //----------------------------------------------------------------------

    public function testConstructionSetsStatusCode204()
    {
        $response = new PrefilightResponse();

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testConstructionSetsXStatusCodeHeader()
    {
        $response = new PrefilightResponse();

        $this->assertTrue($response->headers->has('X-Status-Code'));
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->headers->get('X-Status-Code'));
    }

    public function testConstructionHasEmptyContent()
    {
        $response = new PrefilightResponse();

        $this->assertSame('', $response->getContent());
    }

    //----------------------------------------------------------------------
    // addAllowedMethod / getAllowedMethods
    //----------------------------------------------------------------------

    public function testGetAllowedMethodsInitiallyEmpty()
    {
        $response = new PrefilightResponse();

        $this->assertSame([], $response->getAllowedMethods());
    }

    public function testAddAllowedMethodAddsMethod()
    {
        $response = new PrefilightResponse();
        $response->addAllowedMethod('GET');

        $this->assertSame(['GET'], $response->getAllowedMethods());
    }

    public function testAddMultipleAllowedMethods()
    {
        $response = new PrefilightResponse();
        $response->addAllowedMethod('GET');
        $response->addAllowedMethod('POST');
        $response->addAllowedMethod('PUT');

        $this->assertSame(['GET', 'POST', 'PUT'], $response->getAllowedMethods());
    }

    //----------------------------------------------------------------------
    // freeze / isFrozen
    //----------------------------------------------------------------------

    public function testIsFrozenInitiallyFalse()
    {
        $response = new PrefilightResponse();

        $this->assertFalse($response->isFrozen());
    }

    public function testFreezeSetsFrozenToTrue()
    {
        $response = new PrefilightResponse();
        $response->freeze();

        $this->assertTrue($response->isFrozen());
    }

    public function testFreezeIsIdempotent()
    {
        $response = new PrefilightResponse();
        $response->freeze();
        $response->freeze();

        $this->assertTrue($response->isFrozen());
    }
}
