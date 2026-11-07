<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\StorefrontFormat;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $data = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics']);
        $brand = Brand::firstOrCreate(['slug' => 'e24'], ['name' => 'E24']);

        return Product::create(array_merge(['name' => 'Everyday Lamp', 'slug' => 'lamp', 'price' => 25, 'category_id' => $category->id, 'brand_id' => $brand->id, 'status' => 'active'], $data));
    }

    public function test_home_and_listing_only_show_published_products(): void
    {
        $this->product(['is_featured' => true]);
        $this->product(['slug' => 'draft', 'name' => 'Secret draft', 'status' => 'draft']);
        $deleted = $this->product(['slug' => 'deleted', 'name' => 'Deleted item']);
        $deleted->delete();
        foreach (['/', '/products'] as $path) {
            $this->get($path)->assertOk()->assertSee('Everyday Lamp')->assertDontSee('Secret draft')->assertDontSee('Deleted item');
        }
    }

    public function test_filters_sort_and_pagination_are_stable(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->product(['slug' => 'lamp-'.$i, 'price' => 30]);
        }
        $this->product(['slug' => 'cheap', 'price' => 5, 'name' => 'Cheap lamp']);
        $url = '/products?search=lamp&category=electronics&brand=e24&min_price=20&max_price=40&sort=price_low';
        $response = $this->get($url)->assertOk()->assertDontSee('Cheap lamp');
        $products = $response->viewData('products');
        $this->assertCount(12, $products);
        $this->assertSame(14, $products->total());
        $this->assertSame(range(14, 3), $products->pluck('id')->all());
        $this->assertStringContainsString('min_price=20', $products->nextPageUrl());
        $this->get($url.'&page=2')->assertOk()->assertViewHas('products', fn ($items) => $items->pluck('id')->all() === [2, 1]);
    }

    public function test_invalid_query_inputs_are_rejected_without_redirect_loops(): void
    {
        foreach (['sort=invalid', 'page=0', 'search[]=bad', 'min_price=50&max_price=10', 'max_price=-1'] as $query) {
            $this->get('/products?'.$query)->assertStatus(422)->assertSee('Check your search filters');
        }
    }

    public function test_product_detail_exposes_variant_prices_and_net_stock_and_escapes_content(): void
    {
        $product = $this->product(['description' => '<script>alert(1)</script>', 'short_description' => '<script>alert(1)</script>']);
        $product->variants()->create(['name' => 'Blue', 'sku' => 'BLUE', 'price' => 35, 'stock_quantity' => 10, 'reserved_quantity' => 4]);
        $product->variants()->create(['name' => 'Red', 'sku' => 'RED', 'price' => 45, 'stock_quantity' => 0]);
        $product->variants()->create(['name' => 'Hidden option', 'sku' => 'OFF', 'price' => 50, 'status' => 'inactive']);
        $deleted = $product->variants()->create(['name' => 'Deleted variant', 'sku' => 'DEL', 'price' => 50]);
        $deleted->delete();
        $this->get('/products/lamp')->assertOk()->assertSee('data-price="35.00"', false)->assertSee('data-stock="6"', false)->assertSee('Out of stock')->assertSee('Unavailable')->assertSee('disabled', false)->assertDontSee('Deleted variant')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false)->assertDontSee('Add to cart')->assertSee('product-placeholder.svg');
    }

    public function test_missing_unpublished_and_deleted_products_return_404(): void
    {
        $this->product(['status' => 'inactive']);
        $this->get('/products/lamp')->assertNotFound();
        $this->get('/products/missing')->assertNotFound();
        Product::first()->delete();
        $this->get('/products/lamp')->assertNotFound();
    }

    public function test_availability_uses_only_active_non_deleted_variants_with_net_stock(): void
    {
        $available = $this->product(['slug' => 'available', 'name' => 'Available lamp']);
        $available->variants()->create(['sku' => 'YES', 'name' => 'Available', 'price' => '20.00', 'stock_quantity' => 5, 'reserved_quantity' => 4]);
        $reserved = $this->product(['slug' => 'reserved', 'name' => 'Reserved lamp']);
        $reserved->variants()->create(['sku' => 'HELD', 'name' => 'Reserved', 'price' => '20.00', 'stock_quantity' => 5, 'reserved_quantity' => 5]);
        $inactive = $this->product(['slug' => 'inactive-variant', 'name' => 'Inactive lamp']);
        $inactive->variants()->create(['sku' => 'INACTIVE', 'name' => 'Inactive', 'price' => '20.00', 'status' => 'inactive', 'stock_quantity' => 5]);
        $this->get('/products?availability=in_stock')->assertOk()->assertViewHas('products', fn ($items) => $items->pluck('id')->all() === [$available->id]);
        $this->get('/products?availability=unavailable')->assertOk()->assertViewHas('products', fn ($items) => $items->total() === 2);
        $this->get('/products?availability=invalid')->assertStatus(422);
    }

    public function test_storefront_formats_decimal_prices_without_float_rounding(): void
    {
        $this->assertSame('$9,999,999,999.99', StorefrontFormat::money('9999999999.99'));
        $this->assertSame('$12.50', StorefrontFormat::money('12.50'));
    }

    public function test_navigation_tracks_home_catalog_category_and_product_detail(): void
    {
        $this->product();
        foreach (['/' => 'Home', '/products' => 'All products', '/products?category=electronics' => 'Electronics', '/products/lamp' => 'Electronics'] as $url => $label) {
            $response = $this->get($url)->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $selected = $xpath->query('//nav[@id="category-navigation"]/a[@aria-current="page"]');
            $this->assertCount(1, $selected, $url);
            $this->assertSame($label, trim($selected->item(0)->textContent), $url);
        }
    }

    public function test_product_age_formats_minutes_hours_days_weeks_and_older_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-07 12:00:00', 'Asia/Phnom_Penh'));
        foreach ([10 => '10m', 300 => '5h', 5760 => '4d', 10080 => '1w', 40320 => '10-Oct-2026'] as $minutes => $expected) {
            $this->assertSame($expected, StorefrontFormat::productAge(now()->subMinutes($minutes)));
        }
        $this->assertSame('9-Jan-2006', StorefrontFormat::productAge(CarbonImmutable::parse('2006-01-09', 'Asia/Phnom_Penh')));
        $this->assertSame('1m', StorefrontFormat::productAge(now()));
        $product = $this->product(['location' => 'Siem Reap']);
        $product->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $this->get('/products')->assertOk()->assertSee('Siem Reap')->assertSee('10m')->assertDontSee('Created:');
    }

    public function test_recommendations_fall_back_to_other_published_categories(): void
    {
        $product = $this->product();
        $category = Category::create(['name' => 'Other', 'slug' => 'other']);
        $other = $this->product(['slug' => 'other-item', 'category_id' => $category->id]);
        $this->product(['slug' => 'hidden-item', 'status' => 'draft']);
        $this->get('/products/lamp')->assertOk()->assertSee('You might also like')->assertDontSee('About this product')
            ->assertViewHas('related', fn ($items) => $items->pluck('id')->all() === [$other->id]);
    }
}
