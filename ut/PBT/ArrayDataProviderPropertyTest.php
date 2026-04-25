<?php
/**
 * Property-Based Tests for ArrayDataProvider (oasis/utils ^3.0).
 *
 * Feature: php85-phase5-validation-stabilization
 *
 * Property 1: ArrayDataProvider round-trip invariant
 * Property 2: ArrayDataProvider non-existent key error condition
 *
 * 使用 Eris 生成随机关联数组，验证 ArrayDataProvider 的核心 API
 * （has / get / getOptional）在 ^3.0 升级后行为正确。
 *
 * Validates: R12 AC1, R12 AC2, R12 AC3
 */

namespace Oasis\Mlib\Http\Test\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\Utils\ArrayDataProvider;
use Oasis\Mlib\Utils\DataType;
use Oasis\Mlib\Utils\Exceptions\MandatoryValueMissingException;
use PHPUnit\Framework\TestCase;

class ArrayDataProviderPropertyTest extends TestCase
{
    use TestTrait;

    // ─── Property 1: ArrayDataProvider round-trip invariant ─────────

    /**
     * Feature: php85-phase5-validation-stabilization, Property 1: ArrayDataProvider round-trip invariant
     *
     * For any valid associative array (key: string, value: string|int|float|bool|array),
     * constructing an ArrayDataProvider and querying each key yields:
     *   - has(key) returns true
     *   - get(key, DataType::Mixed) returns the original value
     *   - getOptional(key, DataType::Mixed) returns the original value
     *
     * Note: null values are excluded because ArrayDataProvider::getValue() returns
     * null for both missing keys and null-valued keys, making has() return false
     * for null values. This is by-design behavior, not a bug.
     *
     * Validates: R12 AC1, R12 AC2
     */
    public function testRoundTripInvariant(): void
    {
        $this->forAll(
            Generators::associative([
                'numEntries' => Generators::choose(1, 10),
            ])
        )->then(function (array $params) {
            $numEntries = $params['numEntries'];
            $original = $this->generateNonNullAssociativeArray($numEntries);

            $dp = new ArrayDataProvider($original);

            foreach ($original as $key => $expectedValue) {
                $this->assertTrue(
                    $dp->has($key),
                    "has('$key') should return true for existing key"
                );

                $actualGet = $dp->get($key, DataType::Mixed);
                $this->assertSame(
                    $expectedValue,
                    $actualGet,
                    "get('$key', DataType::Mixed) should return the original value"
                );

                $actualGetOptional = $dp->getOptional($key, DataType::Mixed);
                $this->assertSame(
                    $expectedValue,
                    $actualGetOptional,
                    "getOptional('$key', DataType::Mixed) should return the original value"
                );
            }
        });
    }

    /**
     * Feature: php85-phase5-validation-stabilization, Property 1 (getMandatory variant):
     * ArrayDataProvider round-trip invariant via getMandatory
     *
     * For any valid associative array, getMandatory(key, DataType::Mixed) returns
     * the original value for every existing key.
     *
     * Validates: R12 AC1, R12 AC2
     */
    public function testRoundTripInvariantViaMandatory(): void
    {
        $this->forAll(
            Generators::choose(1, 10)
        )->then(function (int $numEntries) {
            $original = $this->generateNonNullAssociativeArray($numEntries);

            $dp = new ArrayDataProvider($original);

            foreach ($original as $key => $expectedValue) {
                $actualMandatory = $dp->getMandatory($key, DataType::Mixed);
                $this->assertSame(
                    $expectedValue,
                    $actualMandatory,
                    "getMandatory('$key', DataType::Mixed) should return the original value"
                );
            }
        });
    }

    // ─── Property 2: ArrayDataProvider non-existent key error condition ──

    /**
     * Feature: php85-phase5-validation-stabilization, Property 2: ArrayDataProvider non-existent key error condition
     *
     * For any ArrayDataProvider instance and any key NOT present in the data,
     *   - has(nonExistentKey) returns false
     *   - getMandatory(nonExistentKey) throws MandatoryValueMissingException
     *   - getOptional(nonExistentKey, DataType::Mixed, $default) returns $default
     *
     * Validates: R12 AC3
     */
    public function testNonExistentKeyErrorCondition(): void
    {
        $this->forAll(
            Generators::choose(0, 8),
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 80,
                Generators::string()
            )
        )->then(function (int $numEntries, string $candidateKey) {
            $original = $this->generateNonNullAssociativeArray($numEntries);

            // Ensure candidateKey is NOT in the array
            $nonExistentKey = $candidateKey;
            while (array_key_exists($nonExistentKey, $original)) {
                $nonExistentKey .= '_x';
            }

            $dp = new ArrayDataProvider($original);

            // has() should return false
            $this->assertFalse(
                $dp->has($nonExistentKey),
                "has('$nonExistentKey') should return false for non-existent key"
            );

            // getMandatory() should throw MandatoryValueMissingException
            $thrown = false;
            try {
                $dp->getMandatory($nonExistentKey, DataType::Mixed);
            } catch (MandatoryValueMissingException) {
                $thrown = true;
            }
            $this->assertTrue(
                $thrown,
                "getMandatory('$nonExistentKey') should throw MandatoryValueMissingException for non-existent key"
            );
        });
    }

    /**
     * Feature: php85-phase5-validation-stabilization, Property 2 (getOptional default):
     * Non-existent key returns the specified default via getOptional.
     *
     * Validates: R12 AC3
     */
    public function testNonExistentKeyReturnsDefault(): void
    {
        $this->forAll(
            Generators::choose(0, 8),
            Generators::suchThat(
                fn(string $s) => $s !== '' && strlen($s) <= 80,
                Generators::string()
            )
        )->then(function (int $numEntries, string $candidateKey) {
            $original = $this->generateNonNullAssociativeArray($numEntries);

            $nonExistentKey = $candidateKey;
            while (array_key_exists($nonExistentKey, $original)) {
                $nonExistentKey .= '_x';
            }

            $dp = new ArrayDataProvider($original);

            // getOptional() with default should return the default
            $sentinel = new \stdClass();
            $actual = $dp->getOptional($nonExistentKey, DataType::Mixed, $sentinel);
            $this->assertSame(
                $sentinel,
                $actual,
                "getOptional('$nonExistentKey', DataType::Mixed, \$default) should return \$default for non-existent key"
            );

            // get() without mandatory flag should return null (the default default)
            $actualNull = $dp->get($nonExistentKey, DataType::Mixed);
            $this->assertNull(
                $actualNull,
                "get('$nonExistentKey', DataType::Mixed) should return null for non-existent key"
            );
        });
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Generate a random associative array with non-null values.
     *
     * Keys are alphabetic strings (to avoid dot-delimiter path resolution issues).
     * Values are randomly chosen from: string, int, float, bool, array.
     */
    private function generateNonNullAssociativeArray(int $numEntries): array
    {
        $result = [];
        for ($i = 0; $i < $numEntries; $i++) {
            // Use simple alphabetic keys to avoid dot-delimiter path resolution
            $key = 'key_' . $i . '_' . bin2hex(random_bytes(3));
            $result[$key] = $this->generateRandomValue();
        }

        return $result;
    }

    /**
     * Generate a random non-null value of type string|int|float|bool|array.
     */
    private function generateRandomValue(): string|int|float|bool|array
    {
        $type = random_int(0, 4);

        return match ($type) {
            0 => bin2hex(random_bytes(random_int(1, 20))),        // string
            1 => random_int(-10000, 10000),                       // int
            2 => random_int(-10000, 10000) / max(1, random_int(1, 100)), // float
            3 => (bool)random_int(0, 1),                          // bool
            4 => $this->generateRandomArray(),                    // array
        };
    }

    /**
     * Generate a random nested array (depth 1) for array-type values.
     */
    private function generateRandomArray(): array
    {
        $size = random_int(0, 5);
        $arr = [];
        for ($i = 0; $i < $size; $i++) {
            $arr['sub_' . $i] = match (random_int(0, 3)) {
                0 => bin2hex(random_bytes(random_int(1, 10))),
                1 => random_int(-1000, 1000),
                2 => (bool)random_int(0, 1),
                3 => random_int(-1000, 1000) / max(1, random_int(1, 100)),
            };
        }

        return $arr;
    }
}
