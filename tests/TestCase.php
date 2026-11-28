<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pengaman: tes mengosongkan database (RefreshDatabase). Bila konfigurasi
     * ter-cache (`php artisan optimize` / `config:cache`), phpunit.xml diabaikan
     * dan tes akan memakai database demo. Hentikan sebelum ada yang terhapus.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");
        if ($connection !== 'sqlite' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Tes dihentikan: database \"{$database}\" bukan database tes. "
                .'Jalankan `php artisan optimize:clear` lalu ulangi.'
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Seeder & upload menulis PDF e-statement; arahkan ke disk palsu agar
        // storage/app/private milik demo tidak ikut berubah.
        Storage::fake('local');
    }
}
