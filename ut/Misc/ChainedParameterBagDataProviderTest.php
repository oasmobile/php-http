<?php

namespace Oasis\Mlib\Http\Test\Misc;

use Oasis\Mlib\Http\ChainedParameterBagDataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;

class ChainedParameterBagDataProviderTest extends TestCase
{
    //----------------------------------------------------------------------
    // Construction — invalid argument
    //----------------------------------------------------------------------

    public function testConstructWithNonBagObjectThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only ParameterBag|HeaderBag object can be chained.');

        new ChainedParameterBagDataProvider(new \stdClass());
    }

    public function testConstructWithStringThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);

        new ChainedParameterBagDataProvider('not a bag');
    }

    public function testConstructWithParameterBagSucceeds()
    {
        $provider = new ChainedParameterBagDataProvider(new ParameterBag());
        $this->assertInstanceOf(ChainedParameterBagDataProvider::class, $provider);
    }

    public function testConstructWithHeaderBagSucceeds()
    {
        $provider = new ChainedParameterBagDataProvider(new HeaderBag());
        $this->assertInstanceOf(ChainedParameterBagDataProvider::class, $provider);
    }

    public function testConstructWithMixedBagsSucceeds()
    {
        $provider = new ChainedParameterBagDataProvider(new ParameterBag(), new HeaderBag());
        $this->assertInstanceOf(ChainedParameterBagDataProvider::class, $provider);
    }

    //----------------------------------------------------------------------
    // Bag order priority — first bag wins
    //----------------------------------------------------------------------

    public function testFirstBagTakesPriority()
    {
        $bag1 = new ParameterBag(['key' => 'first']);
        $bag2 = new ParameterBag(['key' => 'second']);

        $provider = new ChainedParameterBagDataProvider($bag1, $bag2);

        $this->assertSame('first', $provider->getOptional('key'));
    }

    public function testFallbackToSecondBagWhenFirstDoesNotHaveKey()
    {
        $bag1 = new ParameterBag(['other' => 'value1']);
        $bag2 = new ParameterBag(['key' => 'value2']);

        $provider = new ChainedParameterBagDataProvider($bag1, $bag2);

        $this->assertSame('value2', $provider->getOptional('key'));
    }

    //----------------------------------------------------------------------
    // ParameterBag — uses get()
    //----------------------------------------------------------------------

    public function testParameterBagGetReturnsValue()
    {
        $bag      = new ParameterBag(['name' => 'hello']);
        $provider = new ChainedParameterBagDataProvider($bag);

        $this->assertSame('hello', $provider->getOptional('name'));
    }

    public function testParameterBagGetReturnsIntegerValue()
    {
        $bag      = new ParameterBag(['count' => 42]);
        $provider = new ChainedParameterBagDataProvider($bag);

        // AbstractDataProvider::getOptional defaults to STRING_TYPE validator,
        // use MIXED_TYPE to get the raw value
        $this->assertSame(42, $provider->getOptional('count', 'requireInt'));
    }

    //----------------------------------------------------------------------
    // HeaderBag — single value returns string
    //----------------------------------------------------------------------

    public function testHeaderBagSingleValueReturnsString()
    {
        $bag      = new HeaderBag(['X-Custom' => 'single-value']);
        $provider = new ChainedParameterBagDataProvider($bag);

        $this->assertSame('single-value', $provider->getOptional('x-custom'));
    }

    //----------------------------------------------------------------------
    // HeaderBag — multiple values return array
    //----------------------------------------------------------------------

    public function testHeaderBagMultipleValuesReturnArray()
    {
        $bag = new HeaderBag();
        $bag->set('X-Multi', ['val1', 'val2'], false);

        $provider = new ChainedParameterBagDataProvider($bag);

        $result = $provider->getOptional('x-multi', 'requireArray');
        $this->assertInternalType('array', $result);
        $this->assertCount(2, $result);
        $this->assertContains('val1', $result);
        $this->assertContains('val2', $result);
    }

    //----------------------------------------------------------------------
    // HeaderBag — zero values return null
    //----------------------------------------------------------------------

    public function testHeaderBagZeroValuesReturnNull()
    {
        $bag = new HeaderBag();
        // Set header with empty array value
        $bag->set('X-Empty', []);

        $provider = new ChainedParameterBagDataProvider($bag);

        // HeaderBag::has() returns false for empty array headers in some Symfony versions,
        // or the get($key, null, false) returns [] which becomes null after count==0 check.
        // Either way, the result should be null (or the key is not found).
        $result = $provider->getOptional('x-empty');
        $this->assertNull($result);
    }

    //----------------------------------------------------------------------
    // All bags have no key — returns null
    //----------------------------------------------------------------------

    public function testAllBagsNoKeyReturnsNull()
    {
        $bag1 = new ParameterBag(['other1' => 'v1']);
        $bag2 = new HeaderBag(['X-Other2' => 'v2']);

        $provider = new ChainedParameterBagDataProvider($bag1, $bag2);

        $this->assertNull($provider->getOptional('nonexistent'));
    }

    public function testEmptyProviderReturnsNull()
    {
        $provider = new ChainedParameterBagDataProvider();

        $this->assertNull($provider->getOptional('anything'));
    }

    //----------------------------------------------------------------------
    // Mixed bags — ParameterBag before HeaderBag
    //----------------------------------------------------------------------

    public function testParameterBagBeforeHeaderBagPriority()
    {
        $paramBag  = new ParameterBag(['shared' => 'from-param']);
        $headerBag = new HeaderBag(['shared' => 'from-header']);

        $provider = new ChainedParameterBagDataProvider($paramBag, $headerBag);

        $this->assertSame('from-param', $provider->getOptional('shared'));
    }

    public function testHeaderBagBeforeParameterBagPriority()
    {
        $headerBag = new HeaderBag(['shared' => 'from-header']);
        $paramBag  = new ParameterBag(['shared' => 'from-param']);

        $provider = new ChainedParameterBagDataProvider($headerBag, $paramBag);

        $this->assertSame('from-header', $provider->getOptional('shared'));
    }
}
