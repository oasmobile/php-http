<?php

namespace Oasis\Mlib\Http\Test\Views;

use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use Oasis\Mlib\Http\SilexKernel;
use Oasis\Mlib\Http\Views\DefaultHtmlRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class DefaultHtmlRendererTest extends TestCase
{
    /**
     * @return SilexKernel
     */
    private function createMinimalKernel()
    {
        return new SilexKernel([], true);
    }

    //----------------------------------------------------------------------
    // renderOnSuccess — __toString object
    //----------------------------------------------------------------------

    public function testRenderOnSuccessWithToStringObject()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $obj = new class {
            public function __toString()
            {
                return 'stringified object';
            }
        };

        $response = $renderer->renderOnSuccess($obj, $kernel);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('stringified object', $response->getContent());
    }

    //----------------------------------------------------------------------
    // renderOnSuccess — boolean
    //----------------------------------------------------------------------

    public function testRenderOnSuccessWithBooleanTrue()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess(true, $kernel);

        $this->assertSame('true', $response->getContent());
    }

    public function testRenderOnSuccessWithBooleanFalse()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess(false, $kernel);

        $this->assertSame('false', $response->getContent());
    }

    //----------------------------------------------------------------------
    // renderOnSuccess — scalar (string, int, float)
    //----------------------------------------------------------------------

    public function testRenderOnSuccessWithString()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess('hello world', $kernel);

        $this->assertSame('hello world', $response->getContent());
    }

    public function testRenderOnSuccessWithInteger()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess(42, $kernel);

        $this->assertSame('42', $response->getContent());
    }

    public function testRenderOnSuccessWithFloat()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess(3.14, $kernel);

        $this->assertSame('3.14', $response->getContent());
    }

    //----------------------------------------------------------------------
    // renderOnSuccess — array (JSON pretty-print with HTML formatting)
    //----------------------------------------------------------------------

    public function testRenderOnSuccessWithArray()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $data     = ['key' => 'value'];
        $response = $renderer->renderOnSuccess($data, $kernel);

        $content = $response->getContent();
        // Array is JSON-encoded with pretty print, spaces replaced with &nbsp; and newlines with <br />
        $expected = nl2br(str_replace(' ', '&nbsp;', json_encode($data, JSON_PRETTY_PRINT)));
        $this->assertSame($expected, $content);
    }

    //----------------------------------------------------------------------
    // renderOnSuccess — unsupported type (e.g. plain object without __toString)
    //----------------------------------------------------------------------

    public function testRenderOnSuccessWithUnsupportedTypeReturnsErrorResponse()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $obj = new \stdClass();

        $response = $renderer->renderOnSuccess($obj, $kernel);

        // Unsupported type triggers renderOnException internally with a RuntimeException
        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();
        // Content is HTML-encoded (spaces → &nbsp;), so check for HTML-encoded fragments
        $this->assertContains('Unsupported', $content);
        $this->assertContains('RuntimeException', $content);
    }

    //----------------------------------------------------------------------
    // renderOnSuccess — null (not string, not scalar, not array, not object with __toString)
    //----------------------------------------------------------------------

    public function testRenderOnSuccessWithNullReturnsErrorResponse()
    {
        $renderer = new DefaultHtmlRenderer();
        $kernel   = $this->createMinimalKernel();

        $response = $renderer->renderOnSuccess(null, $kernel);

        // null is not is_string, not is_object, not is_bool, not is_scalar, not is_array
        // falls to unsupported type branch
        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();
        $this->assertContains('Unsupported', $content);
    }

    //----------------------------------------------------------------------
    // renderOnException — no Twig available (fallback to JSON serialization)
    //----------------------------------------------------------------------

    public function testRenderOnExceptionWithoutTwigFallsBackToJsonSerialization()
    {
        $renderer      = new DefaultHtmlRenderer();
        $kernel        = $this->createMinimalKernel();
        $exceptionInfo = new WrappedExceptionInfo(new \RuntimeException('test error'), 500);

        $response = $renderer->renderOnException($exceptionInfo, $kernel);

        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();
        // Without Twig, it falls back to renderOnSuccess(jsonSerialize()), which renders the array as HTML
        $this->assertContains('RuntimeException', $content);
        $this->assertContains('test&nbsp;error', $content);
    }

    //----------------------------------------------------------------------
    // renderOnException — Twig available, template renders
    //----------------------------------------------------------------------

    public function testRenderOnExceptionWithTwigRendersTemplate()
    {
        $renderer      = new DefaultHtmlRenderer();
        $exceptionInfo = new WrappedExceptionInfo(new \RuntimeException('twig error'), 404);

        // Create a mock Twig environment that returns a rendered string
        $twig = $this->getMockBuilder(\Twig_Environment::class)
                     ->disableOriginalConstructor()
                     ->getMock();
        $twig->expects($this->once())
             ->method('render')
             ->with('404.twig', $this->anything())
             ->willReturn('<h1>Not Found</h1>');

        // Create a minimal kernel with Twig registered
        $kernel        = $this->createMinimalKernel();
        $kernel['twig'] = $twig;

        $response = $renderer->renderOnException($exceptionInfo, $kernel);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<h1>Not Found</h1>', $response->getContent());
    }

    //----------------------------------------------------------------------
    // renderOnException — Twig available but template not found (fallback)
    //----------------------------------------------------------------------

    public function testRenderOnExceptionWithTwigTemplateNotFoundFallsBack()
    {
        $renderer      = new DefaultHtmlRenderer();
        $exceptionInfo = new WrappedExceptionInfo(new \RuntimeException('missing template'), 500);

        // Create a mock Twig environment that throws Twig_Error_Loader
        $twig = $this->getMockBuilder(\Twig_Environment::class)
                     ->disableOriginalConstructor()
                     ->getMock();
        $twig->expects($this->once())
             ->method('render')
             ->willThrowException(new \Twig_Error_Loader('Template not found'));

        $kernel        = $this->createMinimalKernel();
        $kernel['twig'] = $twig;

        $response = $renderer->renderOnException($exceptionInfo, $kernel);

        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();
        // Falls back to renderOnSuccess(jsonSerialize())
        $this->assertContains('RuntimeException', $content);
        $this->assertContains('missing&nbsp;template', $content);
    }
}
