<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pesan API mengikuti header Accept-Language dari aplikasi. */
class ApiLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pesan_login_salah_mengikuti_bahasa(): void
    {
        $body = ['username' => 'lumina', 'pin' => '000000'];

        // Tanpa header (kosong; request test Laravel selalu membawa en-us): bahasa default
        // aplikasi (APP_LOCALE=id). Diuji pertama karena
        // dalam satu test locale aplikasi terbawa antar-request.
        $this->postJson('/api/auth/login', $body, ['Accept-Language' => ''])
            ->assertUnauthorized()->assertJson(['message' => 'User ID atau PIN salah.']);

        $this->postJson('/api/auth/login', $body, ['Accept-Language' => 'en'])
            ->assertUnauthorized()->assertJson(['message' => 'Incorrect User ID or PIN.']);

        $this->postJson('/api/auth/login', $body, ['Accept-Language' => 'id-ID'])
            ->assertUnauthorized()->assertJson(['message' => 'User ID atau PIN salah.']);
    }

    public function test_pesan_transfer_mengikuti_bahasa(): void
    {
        $token = $this->postJson('/api/auth/login', ['username' => 'lumina', 'pin' => '123456'])->json('token');
        $transfer = ['payee_name' => 'Bunga', 'bank' => 'Lumina', 'number' => '8820049912', 'amount' => 999999999, 'pin' => '123456'];

        $this->withToken($token)->postJson('/api/transfers', $transfer, ['Accept-Language' => 'en'])
            ->assertStatus(422)->assertJson(['message' => 'Insufficient balance.']);
    }
}
