<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nenhum teste grava arquivo de cliente em storage/ nem no bucket.
        Storage::fake('documents');
        Storage::fake('fiscal');
    }
}
