<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Server yang membuang header Authorization: token dikirim lewat X-Authorization. */
class AuthorizationFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_lewat_x_authorization_diterima(): void
    {
        $this->seed();
        $token = User::where('username', 'lumina')->firstOrFail()->createToken('t')->plainTextToken;

        $this->getJson('/api/accounts/me', ['X-Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('data.number', '8820031475');
    }

    public function test_tanpa_token_tetap_ditolak(): void
    {
        $this->getJson('/api/accounts/me', ['X-Authorization' => 'Bearer salah'])->assertUnauthorized();
    }
}
