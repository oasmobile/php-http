<?php
declare(strict_types=1);

/**
 * Controller for CORS scenario tests.
 *
 * Provides simple endpoints that return JSON data, used to verify
 * CORS header behavior in various configurations.
 */

namespace Oasis\Mlib\Http\Test\Helpers\Controllers;

class ScenarioCorsController
{
    public function home(): array
    {
        return [
            'controller_called' => true,
            'action'            => 'home',
        ];
    }

    public function resource(): array
    {
        return [
            'controller_called' => true,
            'action'            => 'resource',
        ];
    }

    public function apiData(): array
    {
        return [
            'controller_called' => true,
            'action'            => 'apiData',
        ];
    }

    public function secured(): array
    {
        return [
            'controller_called' => true,
            'action'            => 'secured',
        ];
    }
}
