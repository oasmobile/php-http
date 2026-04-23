<?php

namespace Oasis\Mlib\Http\Test\Views;

use Oasis\Mlib\Http\Views\DefaultHtmlRenderer;
use Oasis\Mlib\Http\Views\JsonApiRenderer;
use Oasis\Mlib\Http\Views\RouteBasedResponseRendererResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;

class RouteBasedResponseRendererResolverTest extends TestCase
{
    //----------------------------------------------------------------------
    // html format → DefaultHtmlRenderer
    //----------------------------------------------------------------------

    public function testResolveRequestReturnsDefaultHtmlRendererForHtmlFormat()
    {
        $resolver = new RouteBasedResponseRendererResolver();
        $request  = Request::create('/', 'GET');
        $request->attributes->set('format', 'html');

        $renderer = $resolver->resolveRequest($request);

        $this->assertInstanceOf(DefaultHtmlRenderer::class, $renderer);
    }

    //----------------------------------------------------------------------
    // page format → DefaultHtmlRenderer
    //----------------------------------------------------------------------

    public function testResolveRequestReturnsDefaultHtmlRendererForPageFormat()
    {
        $resolver = new RouteBasedResponseRendererResolver();
        $request  = Request::create('/', 'GET');
        $request->attributes->set('format', 'page');

        $renderer = $resolver->resolveRequest($request);

        $this->assertInstanceOf(DefaultHtmlRenderer::class, $renderer);
    }

    //----------------------------------------------------------------------
    // api format → JsonApiRenderer
    //----------------------------------------------------------------------

    public function testResolveRequestReturnsJsonApiRendererForApiFormat()
    {
        $resolver = new RouteBasedResponseRendererResolver();
        $request  = Request::create('/', 'GET');
        $request->attributes->set('format', 'api');

        $renderer = $resolver->resolveRequest($request);

        $this->assertInstanceOf(JsonApiRenderer::class, $renderer);
    }

    //----------------------------------------------------------------------
    // json format → JsonApiRenderer
    //----------------------------------------------------------------------

    public function testResolveRequestReturnsJsonApiRendererForJsonFormat()
    {
        $resolver = new RouteBasedResponseRendererResolver();
        $request  = Request::create('/', 'GET');
        $request->attributes->set('format', 'json');

        $renderer = $resolver->resolveRequest($request);

        $this->assertInstanceOf(JsonApiRenderer::class, $renderer);
    }

    //----------------------------------------------------------------------
    // Unknown format → InvalidConfigurationException
    //----------------------------------------------------------------------

    public function testResolveRequestThrowsExceptionForUnknownFormat()
    {
        $resolver = new RouteBasedResponseRendererResolver();
        $request  = Request::create('/', 'GET');
        $request->attributes->set('format', 'xml');

        $this->setExpectedException(
            InvalidConfigurationException::class,
            'Unsupported response format xml'
        );

        $resolver->resolveRequest($request);
    }

    //----------------------------------------------------------------------
    // Format priority: 'format' attribute first, fallback to '_format'
    //----------------------------------------------------------------------

    public function testResolveRequestPrefersFormatOverUnderscoreFormat()
    {
        $resolver = new RouteBasedResponseRendererResolver();
        $request  = Request::create('/', 'GET');
        $request->attributes->set('format', 'api');
        $request->attributes->set('_format', 'html');

        $renderer = $resolver->resolveRequest($request);

        // 'format' takes priority → JsonApiRenderer
        $this->assertInstanceOf(JsonApiRenderer::class, $renderer);
    }

    public function testResolveRequestFallsBackToUnderscoreFormat()
    {
        $resolver = new RouteBasedResponseRendererResolver();
        $request  = Request::create('/', 'GET');
        // No 'format' attribute set, only '_format'
        $request->attributes->set('_format', 'json');

        $renderer = $resolver->resolveRequest($request);

        $this->assertInstanceOf(JsonApiRenderer::class, $renderer);
    }

    public function testResolveRequestDefaultsToHtmlWhenNoFormatSet()
    {
        $resolver = new RouteBasedResponseRendererResolver();
        $request  = Request::create('/', 'GET');
        // No format attributes set at all

        $renderer = $resolver->resolveRequest($request);

        // Default is 'html' → DefaultHtmlRenderer
        $this->assertInstanceOf(DefaultHtmlRenderer::class, $renderer);
    }
}
