<?php
/**
 * Property-Based Tests for AbstractSmartViewHandler and RouteBasedResponseRendererResolver.
 *
 * Feature: php85-phase4-language-adaptation
 *
 * Property 4: MIME type matching strict comparison invariance
 * Property 5: Format-to-renderer mapping correctness
 *
 * 使用 Eris 生成随机 MIME type 和 format 字符串，
 * 验证视图处理器在各种输入下的行为正确性。
 *
 * Ref: Requirements 16.1, 16.2, 16.3
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\Test\Helpers\Views\ConcreteSmartViewHandler;
use Oasis\Mlib\Http\Views\DefaultHtmlRenderer;
use Oasis\Mlib\Http\Views\JsonApiRenderer;
use Oasis\Mlib\Http\Views\RouteBasedResponseRendererResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;

class ViewHandlerPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 4: MIME type matching strict comparison invariance ─

    /**
     * Feature: php85-phase4-language-adaptation, Property 4: MIME type matching strict comparison invariance
     * For any valid MIME type string, shouldHandle() returns the same boolean result
     * under strict comparison (===) as it would under loose comparison (==).
     * Since all compared values are strings, the behavior is identical.
     *
     * Ref: Requirements 16.1
     */
    public function testMimeTypeMatchingWithStandardTypes(): void
    {
        $mimeGroups = ['text', 'application', 'image', 'audio', 'video', 'multipart', 'font'];
        $mimeSubtypes = ['html', 'json', 'xml', 'plain', 'javascript', 'css', 'png', 'jpeg', 'pdf', 'octet-stream'];

        $this->forAll(
            Generators::elements($mimeGroups),
            Generators::elements($mimeSubtypes),
            Generators::elements($mimeGroups),
            Generators::elements($mimeSubtypes)
        )->then(function (
            string $acceptGroup,
            string $acceptSubtype,
            string $compatGroup,
            string $compatSubtype
        ) {
            $acceptType = "$acceptGroup/$acceptSubtype";
            $compatType = "$compatGroup/$compatSubtype";

            $handler = new ConcreteSmartViewHandler([$compatType]);
            $request = Request::create('/', 'GET');
            $request->headers->set('Accept', $acceptType);

            $result = $handler->shouldHandle($request);

            // The result should be deterministic: true iff groups and subtypes match
            $expectedMatch = (strtolower($acceptGroup) === strtolower($compatGroup))
                && (strtolower($acceptSubtype) === strtolower($compatSubtype));

            $this->assertSame(
                $expectedMatch,
                $result,
                "shouldHandle() for Accept=$acceptType, compatible=$compatType"
            );
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 4 (wildcard invariance):
     * Wildcard MIME types (*\/* and group\/*) always match under both strict and loose comparison.
     *
     * Ref: Requirements 16.1
     */
    public function testMimeTypeWildcardAlwaysMatches(): void
    {
        $mimeGroups = ['text', 'application', 'image', 'audio', 'video'];
        $mimeSubtypes = ['html', 'json', 'xml', 'plain', 'javascript'];

        $this->forAll(
            Generators::elements($mimeGroups),
            Generators::elements($mimeSubtypes)
        )->then(function (string $group, string $subtype) {
            $compatType = "$group/$subtype";
            $handler = new ConcreteSmartViewHandler([$compatType]);

            // */* should always match
            $request = Request::create('/', 'GET');
            $request->headers->set('Accept', '*/*');
            $this->assertTrue(
                $handler->shouldHandle($request),
                "*/* should always match any compatible type"
            );

            // group/* should match same group
            $request2 = Request::create('/', 'GET');
            $request2->headers->set('Accept', "$group/*");
            $this->assertTrue(
                $handler->shouldHandle($request2),
                "$group/* should match $compatType"
            );

            // different_group/* should NOT match
            $otherGroup = ($group === 'text') ? 'application' : 'text';
            $request3 = Request::create('/', 'GET');
            $request3->headers->set('Accept', "$otherGroup/*");
            $this->assertFalse(
                $handler->shouldHandle($request3),
                "$otherGroup/* should NOT match $compatType"
            );
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 4 (empty accept):
     * When Accept header is empty, shouldHandle() always returns true
     * (defaults to *\/*).
     *
     * Ref: Requirements 16.1
     */
    public function testEmptyAcceptAlwaysMatches(): void
    {
        $mimeGroups = ['text', 'application', 'image', 'audio', 'video'];
        $mimeSubtypes = ['html', 'json', 'xml', 'plain'];

        $this->forAll(
            Generators::elements($mimeGroups),
            Generators::elements($mimeSubtypes)
        )->then(function (string $group, string $subtype) {
            $handler = new ConcreteSmartViewHandler(["$group/$subtype"]);
            $request = Request::create('/', 'GET');
            $request->headers->remove('Accept');

            $this->assertTrue(
                $handler->shouldHandle($request),
                'Empty Accept should default to */* and always match'
            );
        });
    }

    // ─── Property 5: Format-to-renderer mapping correctness ─────────

    /**
     * Feature: php85-phase4-language-adaptation, Property 5: Format-to-renderer mapping correctness
     * html/page → DefaultHtmlRenderer, api/json → JsonApiRenderer.
     *
     * Ref: Requirements 16.2
     */
    public function testValidFormatToRendererMapping(): void
    {
        $this->forAll(
            Generators::elements(['html', 'page', 'api', 'json'])
        )->then(function (string $format) {
            $request = Request::create('/', 'GET');
            $request->attributes->set('format', $format);

            $resolver = new RouteBasedResponseRendererResolver();
            $renderer = $resolver->resolveRequest($request);

            if ($format === 'html' || $format === 'page') {
                $this->assertInstanceOf(
                    DefaultHtmlRenderer::class,
                    $renderer,
                    "Format '$format' should resolve to DefaultHtmlRenderer"
                );
            } else {
                $this->assertInstanceOf(
                    JsonApiRenderer::class,
                    $renderer,
                    "Format '$format' should resolve to JsonApiRenderer"
                );
            }
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 5 (error condition):
     * Any format string other than html/page/api/json throws InvalidConfigurationException.
     *
     * Ref: Requirements 16.3
     */
    public function testInvalidFormatThrowsException(): void
    {
        // Generate random strings that are NOT valid formats
        $validFormats = ['html', 'page', 'api', 'json'];

        $this->forAll(
            Generators::suchThat(
                fn(string $s) => $s !== '' && !in_array($s, $validFormats, true) && strlen($s) <= 50,
                Generators::string()
            )
        )->then(function (string $invalidFormat) {
            $request = Request::create('/', 'GET');
            $request->attributes->set('format', $invalidFormat);

            $resolver = new RouteBasedResponseRendererResolver();

            $this->expectException(InvalidConfigurationException::class);
            $resolver->resolveRequest($request);
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 5 (default format):
     * When no 'format' or '_format' attribute is set, defaults to 'html'
     * and returns DefaultHtmlRenderer.
     *
     * Ref: Requirements 16.2
     */
    public function testDefaultFormatResolvesToHtmlRenderer(): void
    {
        $request = Request::create('/', 'GET');
        // No 'format' or '_format' attribute set

        $resolver = new RouteBasedResponseRendererResolver();
        $renderer = $resolver->resolveRequest($request);

        $this->assertInstanceOf(
            DefaultHtmlRenderer::class,
            $renderer,
            'Default format should resolve to DefaultHtmlRenderer'
        );
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 5 (_format fallback):
     * When 'format' is not set but '_format' is, uses '_format'.
     *
     * Ref: Requirements 16.2
     */
    public function testUnderscoreFormatFallback(): void
    {
        $this->forAll(
            Generators::elements(['html', 'page', 'api', 'json'])
        )->then(function (string $format) {
            $request = Request::create('/', 'GET');
            $request->attributes->set('_format', $format);

            $resolver = new RouteBasedResponseRendererResolver();
            $renderer = $resolver->resolveRequest($request);

            if ($format === 'html' || $format === 'page') {
                $this->assertInstanceOf(DefaultHtmlRenderer::class, $renderer);
            } else {
                $this->assertInstanceOf(JsonApiRenderer::class, $renderer);
            }
        });
    }
}
