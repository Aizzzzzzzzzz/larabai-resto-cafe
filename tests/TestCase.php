<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages ask Vite for the built CSS/JS; tests don't need it, so they run on a fresh clone before `npm run build`
        $this->withoutVite();
    }
}
