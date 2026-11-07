<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCartTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertRedirect(route('storefront.cart.index'))
            ->assertSessionHas('storefront_cart.'.$variant->id, 2);
        $this->get('/cart')->assertOk()->assertSee('$49.00');
    }
}
