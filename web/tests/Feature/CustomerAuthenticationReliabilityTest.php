<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomerResetPassword;
use App\Notifications\CustomerVerifyEmail;
use App\Services\CustomerVerificationDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class CustomerAuthenticationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return $overrides + ['name' => 'សុខ ដារា', 'email' => 'buyer@example.com', 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123'];
    }

    public function test_eight_character_password_is_accepted_but_seven_is_rejected(): void
    {
        Notification::fake();
        $this->postJson('/register', $this->registration(['password' => '1234567', 'password_confirmation' => '1234567']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/register', $this->registration(['password' => '12345678', 'password_confirmation' => '12345678']))->assertCreated();
    }

    public function test_256_character_password_is_not_truncated_and_257_is_rejected(): void
    {
        Notification::fake();
        $password = str_repeat('ក', 256);
        $this->postJson('/register', $this->registration(['password' => $password.'ក', 'password_confirmation' => $password.'ក']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/register', $this->registration(['password' => $password, 'password_confirmation' => $password]))->assertCreated();
        $this->assertSame('argon2id', password_get_info(User::firstOrFail()->password)['algoName']);
        $this->post('/logout');
        $this->postJson('/login', ['email' => 'buyer@example.com', 'password' => str_repeat('ក', 255).'ខ'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/login', ['email' => 'buyer@example.com', 'password' => $password])->assertOk();
    }

    public function test_existing_bcrypt_password_still_signs_in_and_is_upgraded(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        DB::table('users')->where('id', $user->id)->update(['password' => password_hash('Old-password-123', PASSWORD_BCRYPT, ['cost' => 4])]);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Old-password-123'])->assertOk();
        $this->assertSame('argon2id', password_get_info($user->fresh()->password)['algoName']);
    }

    public function test_registration_normalizes_email_and_keeps_unicode_names(): void
    {
        Notification::fake();
        $this->postJson('/register', $this->registration(['email' => '  Buyer@Example.COM  ']))
            ->assertCreated()->assertJsonPath('redirect', route('account.profile'));
        $user = User::firstOrFail();
        $this->assertSame('buyer@example.com', $user->email);
        $this->assertSame('សុខ ដារា', $user->name);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('customer_verification_deliveries', ['user_id' => $user->id, 'attempts' => 0]);
        $this->assertNotNull(DB::table('customer_verification_deliveries')->first()->queued_at);
        Notification::assertSentTo($user, CustomerVerifyEmail::class);
    }

    public function test_existing_mixed_case_email_can_sign_in_and_recover_but_cannot_be_registered_again(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'Buyer@Example.COM', 'password' => 'Correct-password-123', 'role' => 'customer', 'status' => 'active']);
        $this->postJson('/register', $this->registration())->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/login', ['email' => 'buyer@example.com', 'password' => 'Correct-password-123'])->assertOk()->assertJsonPath('redirect', route('account.profile'));
        $this->post('/logout');
        $this->post('/forgot-password', ['email' => 'BUYER@example.com'])->assertRedirect();
        Notification::assertSentTo($user, CustomerResetPassword::class);
        $token = Password::broker('users')->createToken($user);
        $this->post('/reset-password', ['email' => 'buyer@example.com', 'token' => $token, 'password' => 'New-password-456', 'password_confirmation' => 'New-password-456'])->assertRedirect(route('login'));
    }

    public function test_database_rejects_normalized_email_duplicates_even_without_validation(): void
    {
        User::factory()->create(['email' => 'Buyer@Example.COM']);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('users')->insert(['name' => 'Duplicate', 'email' => ' buyer@example.com ', 'password' => 'unused']);
    }

    public function test_unique_conflict_after_validation_is_a_field_error_without_sql_or_passwords(): void
    {
        Event::listen('eloquent.creating: '.User::class, function (): void {
            throw (new UniqueConstraintViolationException('sqlite', 'INSERT private_sql', [], new PDOException('conflicting write')))->setIndex('users_email_normalized_unique');
        });
        try {
            $response = $this->postJson('/register', $this->registration())->assertUnprocessable()->assertJsonValidationErrors('email');
            $response->assertDontSee('private_sql')->assertDontSee('Correct-password-123');
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('customer_verification_deliveries', 0);
        } finally {
            Event::forget('eloquent.creating: '.User::class);
        }
    }

    public function test_account_and_verification_request_roll_back_together_on_storage_failure(): void
    {
        $this->partialMock(CustomerVerificationDelivery::class, function ($mock): void {
            $mock->shouldReceive('request')->once()->andThrow(new QueryException('sqlite', 'INSERT private_sql', ['private-binding'], new PDOException('storage unavailable')));
        });
        $this->postJson('/register', $this->registration())->assertStatus(503)->assertDontSee('private_sql')->assertDontSee('private-binding')->assertDontSee('Correct-password-123');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customer_verification_deliveries', 0);
        $this->assertGuest('web');
    }

    public function test_queue_outage_keeps_account_and_pending_delivery_for_scheduled_retry(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Queue unavailable'));
        $this->postJson('/register', $this->registration())->assertCreated();
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseHas('customer_verification_deliveries', ['user_id' => $user->id, 'queued_at' => null, 'attempts' => 1]);
        Notification::fake();
        $this->artisan('customers:deliver-verification')->assertSuccessful();
        Notification::assertNothingSent();
        $this->travel(301)->seconds();
        $this->artisan('customers:deliver-verification')->assertSuccessful();
        Notification::assertSentTo($user, CustomerVerifyEmail::class);
        $this->artisan('customers:deliver-verification')->assertSuccessful();
        Notification::assertSentToTimes($user, CustomerVerifyEmail::class, 1);
    }

    public function test_verification_is_queued_instead_of_sending_mail_in_registration_request(): void
    {
        config(['queue.default' => 'database']);
        $this->postJson('/register', $this->registration())->assertCreated();
        $this->assertDatabaseCount('jobs', 1);
        $job = DB::table('jobs')->first();
        $this->assertStringNotContainsString('Correct-password-123', $job->payload);
        $this->assertNotNull(DB::table('customer_verification_deliveries')->first()->queued_at);
    }

    public function test_invalid_input_does_not_save_or_flash_passwords_and_character_length_is_checked(): void
    {
        $password = str_repeat('ក', 257);
        $this->post('/register', $this->registration(['password' => $password, 'password_confirmation' => $password]))
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->postJson('/register', $this->registration(['email' => ['invalid'], 'name' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors(['email', 'name']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_authentication_pages_are_not_cached(): void
    {
        $this->get('/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/register')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_sign_in_rotates_session_and_a_changed_password_ends_the_old_session(): void
    {
        $user = User::factory()->create(['password' => 'Correct-password-123', 'role' => 'customer', 'status' => 'active']);
        $this->get('/login');
        $oldSessionId = session()->getId();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Correct-password-123'])->assertOk();
        $this->assertNotSame($oldSessionId, session()->getId());
        $user->forceFill(['password' => 'Changed-password-456'])->save();
        $this->get('/account/profile')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_authentication_links_cannot_be_written_to_the_log_mail_transport(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->create(['role' => 'customer', 'status' => 'active', 'email_verified_at' => null]);
        foreach ([new CustomerVerifyEmail, new CustomerResetPassword('private-token')] as $notification) {
            try {
                $notification->shouldSend($user, 'mail');
                $this->fail('Log mail transport must not receive authentication links.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('private mail transport', $exception->getMessage());
                $this->assertStringNotContainsString('private-token', $exception->getMessage());
            }
        }
    }

    public function test_registration_is_rate_limited_before_expensive_work(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/register', [])->assertUnprocessable();
        }
        $this->postJson('/register', [])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('users', 0);
    }
}
