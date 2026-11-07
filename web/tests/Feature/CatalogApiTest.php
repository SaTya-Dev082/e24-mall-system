<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_are_returned_with_children(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Electronics')
            ->assertJsonPath('data.0.children.0.name', 'Phones');
    }

    public function test_products_are_cursor_paginated_and_lightweight(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->getJson('/api/v1/products?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'slug',
                        'price',
                        'compare_price',
                        'primary_image',
                        'rating_avg',
                        'review_count',
                        'sold_count',
                        'stock_status',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_product_detail_returns_images_and_variants(): void
    {
        $this->seed(CatalogSeeder::class);

        $product = Product::query()->where('slug', 'iphone-16-pro')->firstOrFail();

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.name', 'iPhone 16 Pro')
            ->assertJsonCount(1, 'data.images')
            ->assertJsonCount(2, 'data.variants');
    }

    public function test_filters_use_net_variant_stock_and_validate_inputs(): void
    {
        $this->seed(CatalogSeeder::class);
        $product = Product::where('slug', 'iphone-16-pro')->firstOrFail();
        $product->variants()->update(['reserved_quantity' => 0, 'stock_quantity' => 0]);
        $this->getJson('/api/v1/products?search=iphone&category=phones&brand=apple&min_price=1000&max_price=1200&availability=unavailable')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.has_available_stock', false);
        $this->getJson('/api/v1/products?search=IPHONE&availability=in_stock')->assertOk()->assertJsonCount(0, 'data');
        foreach (['limit=0', 'limit=51', 'min_price=40&max_price=20', 'availability=invalid', 'search[]=bad', 'sort=invalid'] as $query) {
            $this->getJson('/api/v1/products?'.$query)->assertUnprocessable()->assertJsonStructure(['errors']);
        }
    }

    public function test_cursor_tie_breaker_prevents_missing_equal_price_products(): void
    {
        $this->seed(CatalogSeeder::class);
        Product::query()->update(['price' => 50, 'created_at' => '2026-01-01 00:00:00']);
        foreach (['newest', 'price_low', 'price_high'] as $sort) {
            $cursor = null;
            $ids = [];
            do {
                $response = $this->getJson('/api/v1/products?'.http_build_query(['sort' => $sort, 'limit' => 2, 'cursor' => $cursor]))->assertOk();
                $ids = array_merge($ids, array_column($response->json('data'), 'id'));
                $cursor = $response->json('meta.next_cursor');
            } while ($cursor !== null);
            $this->assertCount(Product::where('status', 'active')->count(), $ids);
            $this->assertSame($ids, array_values(array_unique($ids)));
        }
    }

    public function test_drafts_archived_products_and_inactive_variants_are_not_exposed(): void
    {
        $this->seed(CatalogSeeder::class);
        $product = Product::where('slug', 'iphone-16-pro')->firstOrFail();
        $product->variants()->first()->update(['status' => 'inactive']);
        $this->getJson('/api/v1/products/iphone-16-pro')->assertOk()->assertJsonCount(1, 'data.variants');
        $product->update(['status' => 'draft']);
        $this->getJson('/api/v1/products/iphone-16-pro')->assertNotFound();
        $this->getJson('/api/v1/products?search=iphone')->assertOk()->assertJsonCount(0, 'data');
        $product->delete();
        $this->getJson('/api/v1/products/iphone-16-pro')->assertNotFound();
    }
}
