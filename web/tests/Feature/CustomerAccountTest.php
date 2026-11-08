<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\CustomerResetPassword;
use App\Notifications\CustomerVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function option(string $slug = 'lamp', int $stock = 10): ProductVariant
    {
        $category = Category::firstOrCreate(['slug' => 'home'], ['name' => 'Home']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Lamp', 'slug' => $slug, 'status' => 'active', 'price' => '25.00']);

        return $product->variants()->create(['sku' => $slug, 'name' => 'Blue', 'price' => '25.00', 'stock_quantity' => $stock, 'status' => 'active']);
    }

    private function customer(array $data = []): User
    {
        return User::factory()->create($data + ['email' => 'buyer@example.com', 'password' => 'Correct-password-123', 'role' => 'customer', 'status' => 'active']);
    }

    public function test_login_remember_me_sets_persistent_authentication_cookie(): void
    {
        $user = $this->customer();
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-password-123', 'remember' => '1'])
            ->assertRedirect(route('account.profile'))
            ->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_guests_must_sign_in_before_adding_or_choosing_options(): void
    {
        $variant = $this->option();
        $variant->product->variants()->create(['sku' => 'lamp-red', 'name' => 'Red', 'price' => '25.00', 'stock_quantity' => 5, 'status' => 'active']);
        $this->postJson('/cart/products/lamp')->assertUnauthorized()->assertJsonPath('redirect', route('login'))->assertJsonMissingPath('options');
        $this->post('/cart/products/lamp', ['variant' => $variant->id, 'intent' => 'buy_now'])->assertRedirect(route('login'))->assertSessionMissing('storefront_cart');
        $this->assertDatabaseCount('cart_items', 0);
        $user = $this->customer();
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-password-123'])->assertRedirect(route('storefront.products.show', 'lamp'));
        $this->postJson('/cart/products/lamp')->assertStatus(409)->assertJsonCount(2, 'options');
        $this->postJson('/cart/products/lamp', ['variant' => $variant->id])->assertOk()->assertJsonPath('count', 1);
    }

    public function test_registration_uses_customer_role_and_rejects_privilege_fields(): void
    {
        Notification::fake();
        $data = ['name' => 'Buyer', 'email' => 'buyer@example.com', 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123'];
        $this->post('/register', $data + ['role' => 'admin'])->assertSessionHasErrors('role');
        $this->assertDatabaseCount('users', 0);
        $this->post('/register', $data)->assertRedirect(route('account.profile'));
        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('users', ['email' => 'buyer@example.com', 'role' => 'customer', 'status' => 'active']);
        $this->assertTrue(Hash::check($data['password'], User::first()->password));
        $this->get('/account/profile')->assertOk()->assertSee('My profile');
    }

    public function test_guest_checkout_returns_to_review_after_login_and_keeps_selection(): void
    {
        $variant = $this->option();
        $user = $this->customer();
        $this->withSession(['storefront_cart' => [$variant->id => 2], 'storefront_cart_merge_key' => str_repeat('a', 64)]);
        $this->get('/checkout')->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-password-123'])->assertRedirect(route('checkout.index'));
        $this->get('/checkout')->assertOk()->assertSee('Review selected items')->assertSee('$50.00')->assertSee('Ordering is not enabled yet');
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $variant->id, 'quantity' => 2, 'is_selected' => true]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $variant->fresh()->reserved_quantity);
    }

    public function test_account_cart_survives_logout_and_does_not_leak_to_next_user(): void
    {
        $variant = $this->option();
        $user = $this->customer();
        $other = $this->customer(['email' => 'other@example.com']);
        $this->actingAs($user, 'web')->post('/cart/products/lamp', ['quantity' => 2]);
        $this->post('/logout')->assertRedirect(route('home'));
        $this->get('/cart')->assertSee('Your cart is empty');
        $this->post('/login', ['email' => $other->email, 'password' => 'Correct-password-123']);
        $this->get('/cart')->assertSee('Your cart is empty');
        $this->patch('/cart/items/'.$variant->id, ['quantity' => 1])->assertNotFound();
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-password-123']);
        $this->get('/cart')->assertViewHas('count', 2)->assertSee('$50.00');
    }

    public function test_merge_caps_guest_quantities_preserves_selection_and_is_not_repeated(): void
    {
        $variant = $this->option('lamp', 5);
        $other = $this->option('chair');
        $user = $this->customer();
        $this->actingAs($user, 'web')->post('/cart/products/lamp', ['quantity' => 3]);
        $this->post('/logout');
        $this->withSession(['storefront_cart' => [$variant->id => 3, $other->id => 1], 'storefront_cart_merge_key' => str_repeat('b', 64)]);
        $this->post('/cart/selection', ['selected' => [$variant->id]]);
        $guestState = session('storefront_cart');
        $mergeKey = session('storefront_cart_merge_key');
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-password-123'])->assertSessionHas('cart_notice');
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $variant->id, 'quantity' => 5]);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $other->id, 'is_selected' => false]);
        $this->post('/logout');
        $this->withSession(['storefront_cart' => $guestState, 'storefront_cart_merge_key' => $mergeKey]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-password-123']);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $variant->id, 'quantity' => 5]);
        $this->assertDatabaseCount('cart_merge_receipts', 1);
    }

    public function test_inactive_deleted_admin_and_bad_password_cannot_sign_in(): void
    {
        $user = $this->customer(['status' => 'blocked']);
        $credentials = ['email' => $user->email, 'password' => 'Correct-password-123'];
        $this->post('/login', $credentials)->assertSessionHasErrors('email');
        $user->forceFill(['status' => 'active', 'deleted_at' => now()])->save();
        $this->post('/login', $credentials)->assertSessionHasErrors('email');
        $user->forceFill(['deleted_at' => null, 'role' => 'admin'])->save();
        $this->post('/login', $credentials)->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_blocked_customer_is_logged_out_and_password_change_requires_current_password(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web')->get('/account/profile')->assertOk();
        $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'New-password-456', 'password_confirmation' => 'New-password-456'])->assertSessionHasErrors('current_password');
        $this->put('/account/password', ['current_password' => 'Correct-password-123', 'password' => 'New-password-456', 'password_confirmation' => 'New-password-456'])->assertRedirect();
        $this->assertTrue(Hash::check('New-password-456', $user->fresh()->password));
        $this->get('/account/profile')->assertOk();
        $user->update(['status' => 'blocked']);
        $this->get('/account/profile')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_cart_mutations_return_server_rendered_json_and_current_totals(): void
    {
        $variant = $this->option();
        $this->withSession(['storefront_cart' => [$variant->id => 1]]);
        $this->patchJson('/cart/items/'.$variant->id, ['quantity' => 2])->assertOk()->assertJsonPath('count', 2)->assertJsonStructure(['html']);
        $this->postJson('/cart/selection', ['selected' => []])->assertOk()->assertJsonPath('count', 2);
        $this->get('/cart')->assertViewHas('subtotal', '0.00');
        $this->get('/checkout')->assertRedirect(route('login'));
        $this->deleteJson('/cart/items/'.$variant->id)->assertOk()->assertJsonPath('count', 0);
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }

    public function test_addresses_are_owned_and_have_one_default(): void
    {
        $user = $this->customer();
        $other = $this->customer(['email' => 'other@example.com']);
        $data = ['full_name' => 'Buyer', 'phone' => '012345678', 'province' => 'Phnom Penh', 'street_address' => 'Street 1'];
        $this->actingAs($user, 'web')->post('/account/addresses', $data)->assertRedirect(route('account.addresses'));
        $first = DB::table('addresses')->first();
        $this->assertTrue((bool) $first->is_default);
        $this->post('/account/addresses', $data + ['is_default' => true]);
        $this->assertSame(1, DB::table('addresses')->where('user_id', $user->id)->where('is_default', true)->count());
        $this->get('/account/addresses')->assertOk();
        $this->get('/account/addresses/'.$first->id.'/edit')->assertOk();
        Auth::guard('web')->logout();
        $this->withSession(['customer_password_hash' => $other->password])->actingAs($other, 'web');
        $this->get('/account/addresses/'.$first->id.'/edit')->assertNotFound();
        $this->put('/account/addresses/'.$first->id, $data)->assertNotFound();
        $this->delete('/account/addresses/'.$first->id)->assertNotFound();
    }

    public function test_checkout_rejects_empty_or_unavailable_selected_lines(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web')->get('/checkout')->assertRedirect(route('storefront.cart.index'));
        $variant = $this->option();
        $this->post('/cart/products/lamp');
        $variant->update(['status' => 'inactive']);
        $this->get('/checkout')->assertRedirect(route('storefront.cart.index'))->assertSessionHasErrors('cart');
    }

    public function test_password_recovery_is_customer_only_and_reset_tokens_are_single_use(): void
    {
        Notification::fake();
        $user = $this->customer();
        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        Notification::assertSentTo($user, CustomerResetPassword::class);
        $token = Password::broker('users')->createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'New-password-456', 'password_confirmation' => 'New-password-456'];
        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk();
        $this->post('/reset-password', $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('New-password-456', $user->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->post('/forgot-password', ['email' => $admin->email])->assertRedirect();
        Notification::assertNotSentTo($admin, CustomerResetPassword::class);
    }

    public function test_email_verification_requires_signed_matching_customer_and_checkout_verification(): void
    {
        Notification::fake();
        $user = $this->customer(['email_verified_at' => null]);
        $this->actingAs($user, 'web')->get('/checkout')->assertRedirect(route('verification.notice'));
        $this->get('/verify-email')->assertOk();
        $this->post('/email/verification-notification')->assertRedirect();
        Notification::assertSentTo($user, CustomerVerifyEmail::class);
        $bad = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id + 1, 'hash' => sha1($user->email)]);
        $this->get($bad)->assertForbidden();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url.'&tampered=true')->assertForbidden();
        $this->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_attempts_are_limited_and_invalid_password_does_not_authenticate(): void
    {
        $user = $this->customer();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-password-123'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'Correct-password-123'])->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_buy_now_only_selects_requested_option_and_quantity_without_charging(): void
    {
        $first = $this->option();
        $second = $this->option('chair');
        $user = $this->customer();
        $this->actingAs($user, 'web')->post('/cart/products/lamp', ['quantity' => 3]);
        $this->post('/cart/products/chair', ['quantity' => 1]);
        $this->post('/cart/products/lamp', ['quantity' => 2, 'intent' => 'buy_now'])->assertRedirect(route('checkout.index'));
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $first->id, 'quantity' => 2, 'is_selected' => true]);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $second->id, 'quantity' => 1, 'is_selected' => false]);
        $this->get('/checkout')->assertOk()->assertSee('$50.00');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
