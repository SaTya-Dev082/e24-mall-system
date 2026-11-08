<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveCustomer;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Retain coverage for legacy guest carts; authentication is tested in CustomerAccountTest.
        $this->withoutMiddleware(EnsureActiveCustomer::class);
    }

    private function option(string $slug = 'lamp'): ProductVariant
    {
        $category = Category::firstOrCreate(['slug' => 'home'], ['name' => 'Home']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Everyday Lamp', 'slug' => $slug, 'price' => '20.00', 'status' => 'active']);

        return $product->variants()->create(['sku' => $slug, 'name' => 'Blue', 'price' => '24.50', 'stock_quantity' => 5, 'reserved_quantity' => 2, 'status' => 'active']);
    }

    public function test_single_option_adds_to_session_cart_without_reserving_stock(): void
    {
        $variant = $this->option();
        $this->from('/products')->post('/cart/products/lamp', ['price' => 1])->assertRedirect('/products')->assertSessionHas('storefront_cart.'.$variant->id, 1);
        $this->post('/cart/products/lamp')->assertSessionHas('storefront_cart.'.$variant->id, 2);
        $this->get('/cart')->assertOk()->assertSee('$49.00')->assertSee('Quantity: 2');
        $this->assertSame(5, $variant->fresh()->stock_quantity);
        $this->assertSame(2, $variant->fresh()->reserved_quantity);
        $this->delete('/cart/items/'.$variant->id)->assertRedirect();
        $this->get('/cart')->assertSee('Your cart is empty');
    }

    public function test_multiple_options_require_selection_and_reject_foreign_variants(): void
    {
        $variant = $this->option();
        $variant->product->variants()->create(['sku' => 'red', 'name' => 'Red', 'price' => '28.00', 'stock_quantity' => 2]);
        $this->post('/cart/products/lamp')->assertRedirect(route('storefront.products.show', 'lamp'))->assertSessionMissing('storefront_cart');
        $this->post('/cart/products/lamp', ['variant' => $variant->id])->assertSessionHas('storefront_cart.'.$variant->id, 1);
        $foreign = $this->option('chair');
        $this->post('/cart/products/lamp', ['variant' => $foreign->id])->assertNotFound();
    }

    public function test_cart_enforces_stock_status_quantity_and_current_prices(): void
    {
        $variant = $this->option();
        $this->post('/cart/products/lamp', ['variant' => $variant->id, 'quantity' => 4])->assertSessionHasErrors('cart');
        $this->post('/cart/products/lamp', ['variant' => $variant->id, 'quantity' => -1])->assertSessionHasErrors('quantity');
        $this->post('/cart/products/lamp', ['variant' => $variant->id])->assertSessionHas('storefront_cart.'.$variant->id, 1);
        $variant->update(['price' => '31.25']);
        $this->get('/cart')->assertSee('$31.25');
        $variant->update(['status' => 'inactive']);
        $this->post('/cart/products/lamp', ['variant' => $variant->id])->assertSessionHasErrors('cart');
        $this->get('/cart')->assertSee('Unavailable or insufficient stock')->assertSee('$0.00');
        $variant->product->update(['status' => 'inactive']);
        $this->post('/cart/products/lamp', ['variant' => $variant->id])->assertNotFound();
    }

    public function test_badges_use_stock_and_created_date_and_cards_link_to_detail(): void
    {
        $variant = $this->option();
        $this->get('/products')->assertOk()->assertSee('compact-new')->assertSee('In Stock')->assertSee('Add to Cart')->assertSee('compact-item-link');
        $variant->update(['stock_quantity' => 2]);
        $variant->product->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->get('/products')->assertSee('Out of Stock')->assertDontSee('class="compact-new"', false)->assertSee('disabled', false);
    }

    public function test_buy_now_adds_selected_quantity_and_opens_cart_for_review(): void
    {
        $variant = $this->option();
        $this->get('/products/lamp')->assertOk()->assertSee('Buy Now')->assertSee('id="detail-quantity"', false);
        $this->post('/cart/products/lamp', ['variant' => $variant->id, 'quantity' => 2, 'intent' => 'buy_now'])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('storefront_cart.'.$variant->id, 2);
        $this->get('/cart')->assertOk()->assertSee('$49.00');
    }

    public function test_cart_quantity_update_checks_session_ownership_stock_and_totals(): void
    {
        $variant = $this->option();
        $this->patch('/cart/items/'.$variant->id, ['quantity' => 2])->assertNotFound();
        $this->post('/cart/products/lamp');
        $this->patch('/cart/items/'.$variant->id, ['quantity' => 3])->assertRedirect(route('storefront.cart.index'))->assertSessionHas('storefront_cart.'.$variant->id, 3);
        $this->get('/cart')->assertOk()->assertSee('$73.50')->assertSee('Cart summary')->assertSee('cart-item-image');
        $this->patch('/cart/items/'.$variant->id, ['quantity' => 4])->assertSessionHasErrors('cart')->assertSessionHas('storefront_cart.'.$variant->id, 3);
        $this->patch('/cart/items/'.$variant->id, ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->assertSame(2, $variant->fresh()->reserved_quantity);
        $variant->update(['status' => 'inactive']);
        $this->patch('/cart/items/'.$variant->id, ['quantity' => 1])->assertSessionHasErrors('cart');
    }

    public function test_selection_changes_subtotal_and_bulk_remove_only_removes_selected_owned_lines(): void
    {
        $first = $this->option();
        $second = $this->option('chair');
        $this->post('/cart/products/lamp');
        $this->post('/cart/products/chair');
        $this->post('/cart/selection', ['selected' => [$first->id]])->assertRedirect(route('storefront.cart.index'));
        $this->get('/cart')->assertViewHas('subtotal', '24.50');
        $this->post('/cart/selection', ['selected' => []])->assertRedirect();
        $this->get('/cart')->assertViewHas('subtotal', '0.00');
        $this->post('/cart/selection', ['selected' => [999999]])->assertNotFound();
        $this->post('/cart/remove-selected', ['selected' => [999999]])->assertNotFound();
        $this->post('/cart/remove-selected', ['selected' => [$first->id]])->assertRedirect()->assertSessionHas('storefront_cart', [$second->id => 1]);
        $this->post('/cart/selection', ['selected' => [$second->id]])->assertRedirect();
        $this->get('/cart')->assertViewHas('subtotal', '24.50');
    }

    public function test_card_json_add_prompts_for_variants_and_updates_cart_without_redirect(): void
    {
        $variant = $this->option();
        $this->postJson('/cart/products/lamp')->assertOk()->assertJsonPath('count', 1);
        $variant->product->variants()->create(['sku' => 'other-choice', 'name' => 'Red', 'price' => '28.00', 'stock_quantity' => 2]);
        $this->postJson('/cart/products/lamp')->assertStatus(409)->assertJsonCount(2, 'options')->assertSessionHas('storefront_cart.'.$variant->id, 1);
        $this->postJson('/cart/products/lamp', ['variant' => $variant->id])->assertOk()->assertJsonPath('count', 2);
        $variant->update(['status' => 'inactive']);
        $this->postJson('/cart/products/lamp', ['variant' => $variant->id])->assertUnprocessable();
    }
}
