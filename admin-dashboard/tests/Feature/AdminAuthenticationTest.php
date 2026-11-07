<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return ['--path' => [realpath(base_path('../web/database/migrations'))], '--realpath' => true];
    }

    private function admin(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'admin', 'status' => 'active'], $attributes));
    }

    public function test_guests_can_view_login_but_not_dashboard(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back.')->assertSee('name="_token"', false);
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_admin_can_sign_in_and_sign_out(): void
    {
        $admin = $this->admin();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertOk()->assertSee('Admin Dashboard')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/login')->assertRedirect('/dashboard');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_invalid_password_customer_blocked_and_deleted_admin_cannot_sign_in(): void
    {
        foreach ([['role' => 'customer'], ['status' => 'blocked'], ['status' => 'inactive'], []] as $attributes) {
            $user = $this->admin($attributes);
            $this->post('/login', ['email' => $user->email, 'password' => empty($attributes) ? 'incorrect' : 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $deleted = $this->admin();
        $deleted->delete();
        $this->post('/login', ['email' => $deleted->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_losing_access_is_signed_out_on_next_request(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $admin->forceFill(['status' => 'blocked'])->save();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_failed_login_attempts_are_rate_limited(): void
    {
        $admin = $this->admin();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $admin->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_admin_command_creates_account_without_overwriting_existing_user(): void
    {
        $this->artisan('admin:create', ['email' => 'owner@example.com', '--name' => 'Store Owner'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-unique-password')
            ->expectsQuestion('Confirm password', 'a-long-unique-password')->assertSuccessful();
        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('a-long-unique-password', $user->password));
        $this->artisan('admin:create', ['email' => 'owner@example.com', '--name' => 'Replacement'])
            ->expectsQuestion('Password (at least 12 characters)', 'a-long-unique-password')
            ->expectsQuestion('Confirm password', 'a-long-unique-password')->assertFailed();
        $this->assertSame('Store Owner', $user->fresh()->name);
    }
}
