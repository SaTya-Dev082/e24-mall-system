<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receive_token(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Customer One',
            'email' => 'customer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'postman',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'customer@example.com')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure(['token']);
    }

    public function test_user_can_login_fetch_profile_and_logout(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $token = $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            'device_name' => 'postman',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertOk();
    }
}
