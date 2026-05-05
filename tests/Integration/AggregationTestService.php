<?php
declare(strict_types=1);

/**
 * Simple service object for testing addControllerInjectedArg().
 *
 * Used by MicroKernelAggregationScenarioTest to verify that custom objects
 * registered via addControllerInjectedArg() are available as controller arguments.
 */

namespace Oasis\Mlib\Http\Test\Integration;

class AggregationTestService
{
    private string $value;

    public function __construct(string $value = 'test-service-value')
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
