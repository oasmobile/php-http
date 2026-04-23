<?php

namespace Oasis\Mlib\Http\Test\Helpers\Views;

use Oasis\Mlib\Http\Views\AbstractSmartViewHandler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Concrete Test_Double for AbstractSmartViewHandler.
 *
 * Exposes shouldHandle() as public and allows configurable compatible types.
 */
class ConcreteSmartViewHandler extends AbstractSmartViewHandler
{
    /** @var array */
    private $compatibleTypes;

    /**
     * @param array $compatibleTypes
     */
    public function __construct(array $compatibleTypes = [])
    {
        $this->compatibleTypes = $compatibleTypes;
    }

    /**
     * @param array $types
     */
    public function setCompatibleTypes(array $types)
    {
        $this->compatibleTypes = $types;
    }

    /**
     * Expose protected shouldHandle() as public for testing.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function shouldHandle(Request $request)
    {
        return parent::shouldHandle($request);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCompatibleTypes()
    {
        return $this->compatibleTypes;
    }
}
