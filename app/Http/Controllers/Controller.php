<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    public const DEMO_MESSAGE = 'Demo only — nothing is saved';

    /**
     * UI scaffold only: answer a form submit without saving anything.
     * Each call is replaced by a real service call when its module is built (development-plan.md §5).
     */
    protected function demoSaved(?string $route = null, array $parameters = []): RedirectResponse
    {
        $redirect = $route ? redirect()->route($route, $parameters) : back();

        return $redirect->with('status', self::DEMO_MESSAGE);
    }
}
