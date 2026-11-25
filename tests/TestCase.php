<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seeder & upload menulis PDF e-statement; arahkan ke disk palsu agar
        // storage/app/private milik demo tidak ikut berubah.
        Storage::fake('local');
    }
}
