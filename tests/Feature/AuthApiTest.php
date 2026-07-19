<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    public function test_login_returns_token_for_valid_user(): void
    {
        $email = 'auth-test-'.Str::random(6).'@littlestars.com';
        User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_forgot_password_returns_success_message(): void
    {
        $email = 'reset-'.Str::random(6).'@littlestars.com';
        User::factory()->create([
            'email' => $email,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $email,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
    }
}
