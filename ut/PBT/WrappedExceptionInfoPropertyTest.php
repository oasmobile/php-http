<?php
/**
 * Property-Based Tests for WrappedExceptionInfo.
 *
 * Feature: php85-phase4-language-adaptation
 *
 * Property 1: Status code normalization invariant
 * Property 2: Serialization code field metamorphic property
 * Property 3: WrappedExceptionInfo JSON round-trip
 *
 * 使用 Eris 生成随机 status code 和 Exception 对象，
 * 基于当前代码验证 property 成立，作为后续修改的回归保护。
 *
 * Ref: Requirements 15.1, 15.2, 15.3, 17.4
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class WrappedExceptionInfoPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 1: Status code normalization invariant ────────────

    /**
     * Feature: php85-phase4-language-adaptation, Property 1: Status code normalization invariant
     * For any integer HTTP status code, getCode() returns 500 when input is 0,
     * otherwise returns the original input code.
     *
     * Ref: Requirements 15.1
     */
    public function testStatusCodeNormalizationInvariant(): void
    {
        $this->forAll(
            Generators::choose(-1000, 1000)
        )->then(function (int $httpStatusCode) {
            $exception = new \RuntimeException('test');
            $info = new WrappedExceptionInfo($exception, $httpStatusCode);

            if ($httpStatusCode === 0) {
                $this->assertSame(
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    $info->getCode(),
                    'getCode() should return 500 when input code is 0'
                );
            } else {
                $this->assertSame(
                    $httpStatusCode,
                    $info->getCode(),
                    'getCode() should return the original input code when non-zero'
                );
            }

            // originalCode always preserves the input
            $this->assertSame(
                $httpStatusCode,
                $info->getOriginalCode(),
                'getOriginalCode() should always return the original input'
            );
        });
    }

    // ─── Property 2: Serialization code field metamorphic property ──

    /**
     * Feature: php85-phase4-language-adaptation, Property 2: Serialization code field metamorphic property
     * For any Exception, serializeException() output contains a 'code' key
     * if and only if the exception's code is non-zero.
     *
     * Ref: Requirements 15.2
     */
    public function testSerializationCodeFieldMetamorphicProperty(): void
    {
        $this->forAll(
            Generators::choose(-1000, 1000)
        )->then(function (int $exceptionCode) {
            $exception = new \RuntimeException('test', $exceptionCode);
            $info = new WrappedExceptionInfo($exception, 500);
            $array = $info->toArray();

            $exceptionData = $array['exception'];

            if ($exceptionCode === 0) {
                $this->assertArrayNotHasKey(
                    'code',
                    $exceptionData,
                    'serializeException() should NOT include "code" key when exception code is 0'
                );
            } else {
                $this->assertArrayHasKey(
                    'code',
                    $exceptionData,
                    'serializeException() should include "code" key when exception code is non-zero'
                );
                $this->assertSame(
                    $exceptionCode,
                    $exceptionData['code'],
                    'serializeException() "code" value should match exception code'
                );
            }
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 2 (nested previous):
     * For an exception chain, each level's serialization follows the same
     * code-field metamorphic property.
     *
     * Ref: Requirements 15.2
     */
    public function testSerializationCodeFieldWithPreviousChain(): void
    {
        $this->forAll(
            Generators::choose(-500, 500),
            Generators::choose(-500, 500)
        )->then(function (int $outerCode, int $innerCode) {
            $inner = new \LogicException('inner', $innerCode);
            $outer = new \RuntimeException('outer', $outerCode, $inner);

            $info = new WrappedExceptionInfo($outer, 500);
            $array = $info->toArray();

            // Verify outer exception code field
            $outerData = $array['exception'];
            if ($outerCode === 0) {
                $this->assertArrayNotHasKey('code', $outerData);
            } else {
                $this->assertArrayHasKey('code', $outerData);
                $this->assertSame($outerCode, $outerData['code']);
            }

            // Verify inner (previous) exception code field
            $this->assertArrayHasKey('previous', $outerData);
            $innerData = $outerData['previous'];
            if ($innerCode === 0) {
                $this->assertArrayNotHasKey('code', $innerData);
            } else {
                $this->assertArrayHasKey('code', $innerData);
                $this->assertSame($innerCode, $innerData['code']);
            }
        });
    }

    // ─── Property 3: WrappedExceptionInfo JSON round-trip ───────────

    /**
     * Feature: php85-phase4-language-adaptation, Property 3: WrappedExceptionInfo JSON round-trip
     * For any WrappedExceptionInfo instance, json_decode(json_encode(toArray()))
     * produces an equivalent array structure.
     *
     * Ref: Requirements 15.3, 17.4
     */
    public function testJsonRoundTrip(): void
    {
        $this->forAll(
            Generators::choose(100, 599),
            Generators::choose(-500, 500)
        )->then(function (int $httpStatusCode, int $exceptionCode) {
            $exception = new \RuntimeException('round-trip test', $exceptionCode);
            $info = new WrappedExceptionInfo($exception, $httpStatusCode);

            $original = $info->toArray();
            $encoded = json_encode($original);
            $this->assertNotFalse($encoded, 'json_encode should succeed');

            $decoded = json_decode($encoded, true);
            $this->assertIsArray($decoded, 'json_decode should produce an array');

            // Verify top-level keys are preserved
            $this->assertArrayHasKey('code', $decoded);
            $this->assertArrayHasKey('exception', $decoded);
            $this->assertArrayHasKey('extra', $decoded);

            // Verify code value round-trips correctly
            $expectedCode = ($httpStatusCode === 0)
                ? Response::HTTP_INTERNAL_SERVER_ERROR
                : $httpStatusCode;
            $this->assertSame($expectedCode, $decoded['code']);

            // Verify exception structure round-trips
            $this->assertSame($original['exception']['type'], $decoded['exception']['type']);
            $this->assertSame($original['exception']['message'], $decoded['exception']['message']);
            $this->assertArrayHasKey('file', $decoded['exception']);
            $this->assertArrayHasKey('line', $decoded['exception']);

            // Verify code field presence is preserved through round-trip
            if ($exceptionCode === 0) {
                $this->assertArrayNotHasKey('code', $decoded['exception']);
            } else {
                $this->assertArrayHasKey('code', $decoded['exception']);
                $this->assertSame($exceptionCode, $decoded['exception']['code']);
            }
        });
    }

    /**
     * Feature: php85-phase4-language-adaptation, Property 3 (with attributes):
     * JSON round-trip preserves extra attributes.
     *
     * Ref: Requirements 15.3, 17.4
     */
    public function testJsonRoundTripWithAttributes(): void
    {
        $this->forAll(
            Generators::choose(100, 599),
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 50,
                Generators::string()
            )
        )->then(function (int $httpStatusCode, string $attrValue) {
            $exception = new \RuntimeException('attr test');
            $info = new WrappedExceptionInfo($exception, $httpStatusCode);
            $info->setAttribute('test_key', $attrValue);

            $original = $info->toArray();
            $decoded = json_decode(json_encode($original), true);

            // Verify extra/attributes round-trip
            $this->assertArrayHasKey('extra', $decoded);
            $this->assertArrayHasKey('test_key', $decoded['extra']);
            $this->assertSame($attrValue, $decoded['extra']['test_key']);
        });
    }
}
