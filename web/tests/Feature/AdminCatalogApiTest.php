<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/categories')
            ->assertUnauthorized();
    }

    public function test_admin_routes_reject_customer_users(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/admin/categories')
            ->assertForbidden();
    }

    public function test_admin_can_create_update_delete_and_restore_category(): void
    {
        $payload = [
            'name' => 'Accessories',
            'status' => 'active',
            'sort_order' => 9,
        ];

        $categoryId = $this->actingAsAdmin()
            ->postJson('/api/v1/admin/categories', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Accessories')
            ->json('data.id');

        $this->actingAsAdmin()
            ->patchJson("/api/v1/admin/categories/{$categoryId}", [
                'name' => 'Tech Accessories',
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tech Accessories')
            ->assertJsonPath('data.slug', 'tech-accessories')
            ->assertJsonPath('data.id', $categoryId);

        $this->actingAsAdmin()
            ->deleteJson("/api/v1/admin/categories/{$categoryId}")
            ->assertNoContent();

        $this->assertSoftDeleted('categories', ['id' => $categoryId]);

        $this->actingAsAdmin()
            ->patchJson("/api/v1/admin/categories/{$categoryId}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $categoryId);
    }

    public function test_admin_cannot_delete_category_with_products(): void
    {
        $this->seed(CatalogSeeder::class);

        $category = Category::query()->where('slug', 'phones')->firstOrFail();

        $this->actingAsAdmin()
            ->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertStatus(409);
    }

    public function test_admin_can_create_update_delete_and_restore_brand(): void
    {
        $brandId = $this->actingAsAdmin()
            ->postJson('/api/v1/admin/brands', [
                'name' => 'Sony',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'sony')
            ->json('data.id');

        $this->actingAsAdmin()
            ->patchJson("/api/v1/admin/brands/{$brandId}", [
                'name' => 'Sony Cambodia',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sony Cambodia');

        $this->actingAsAdmin()
            ->deleteJson("/api/v1/admin/brands/{$brandId}")
            ->assertNoContent();

        $this->assertSoftDeleted('brands', ['id' => $brandId]);

        $this->actingAsAdmin()
            ->patchJson("/api/v1/admin/brands/{$brandId}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $brandId);
    }

    public function test_admin_can_create_update_delete_and_restore_product_with_images_and_variants(): void
    {
        $category = Category::query()->create([
            'name' => 'Accessories',
            'slug' => 'accessories',
            'status' => 'active',
        ]);

        $brand = Brand::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'status' => 'active',
        ]);

        $productId = $this->actingAsAdmin()
            ->postJson('/api/v1/admin/products', [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => 'Travel Backpack',
                'sku' => 'TRAVEL-BACKPACK',
                'short_description' => 'A durable travel bag.',
                'description' => 'Built for weekend trips and daily commutes.',
                'price' => 79.99,
                'compare_price' => 99.99,
                'stock_status' => 'in_stock',
                'status' => 'active',
                'images' => [
                    ['image_url' => 'https://placehold.co/900x900?text=Backpack', 'is_primary' => true],
                ],
                'variants' => [
                    [
                        'sku' => 'TRAVEL-BACKPACK-BLK',
                        'name' => 'Black',
                        'price' => 79.99,
                        'stock_quantity' => 12,
                        'status' => 'active',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'travel-backpack')
            ->assertJsonCount(1, 'data.images')
            ->assertJsonCount(1, 'data.variants')
            ->json('data.id');

        $product = Product::query()->with(['images', 'variants'])->findOrFail($productId);

        $this->actingAsAdmin()
            ->patchJson("/api/v1/admin/products/{$productId}", [
                'name' => 'Travel Backpack Pro',
                'price' => 89.99,
                'status' => 'active',
                'images' => [
                    [
                        'id' => $product->images->first()->id,
                        'image_url' => 'https://placehold.co/900x900?text=Backpack+Pro',
                        'is_primary' => true,
                    ],
                ],
                'variants' => [
                    [
                        'id' => $product->variants->first()->id,
                        'sku' => 'TRAVEL-BACKPACK-BLK',
                        'name' => 'Black / Pro',
                        'price' => 89.99,
                        'status' => 'active',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Travel Backpack Pro')
            ->assertJsonPath('data.slug', 'travel-backpack-pro')
            ->assertJsonPath('data.variants.0.available_stock', 12);

        $this->actingAsAdmin()
            ->deleteJson("/api/v1/admin/products/{$productId}")
            ->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $productId]);

        $this->actingAsAdmin()
            ->patchJson("/api/v1/admin/products/{$productId}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $productId);
    }

    private function actingAsAdmin(): static
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        return $this->actingAs($admin, 'sanctum');
    }
}
