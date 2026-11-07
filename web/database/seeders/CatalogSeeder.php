<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantValue;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $electronics = $this->category('Electronics', 1);
        $phones = $this->category('Phones', 1, $electronics->id);
        $laptops = $this->category('Laptops', 2, $electronics->id);
        $fashion = $this->category('Fashion', 2);
        $shoes = $this->category('Shoes', 1, $fashion->id);

        $apple = $this->brand('Apple');
        $samsung = $this->brand('Samsung');
        $nike = $this->brand('Nike');

        $color = Attribute::query()->firstOrCreate(['name' => 'Color']);
        $storage = Attribute::query()->firstOrCreate(['name' => 'Storage']);
        $size = Attribute::query()->firstOrCreate(['name' => 'Size']);

        $black = $this->attributeValue($color, 'Black');
        $blue = $this->attributeValue($color, 'Blue');
        $white = $this->attributeValue($color, 'White');
        $storage128 = $this->attributeValue($storage, '128GB');
        $storage256 = $this->attributeValue($storage, '256GB');
        $size42 = $this->attributeValue($size, '42');
        $size43 = $this->attributeValue($size, '43');

        $iphone = $this->product([
            'category_id' => $phones->id,
            'brand_id' => $apple->id,
            'name' => 'iPhone 16 Pro',
            'sku' => 'IPHONE-16-PRO',
            'short_description' => 'A flagship phone with excellent camera performance and fast everyday speed.',
            'description' => 'Built for premium mobile shopping demos with multiple storage and color variants.',
            'price' => 1099,
            'compare_price' => 1199,
            'is_featured' => true,
            'rating_avg' => 4.8,
            'review_count' => 128,
            'sold_count' => 430,
        ]);
        $this->images($iphone, 'https://placehold.co/900x900/e2e8f0/0f172a?text=iPhone+16+Pro');
        $this->variant($iphone, 'IPHONE-16-PRO-128-BLK', '128GB / Black', 1099, 20, [[$color, $black], [$storage, $storage128]]);
        $this->variant($iphone, 'IPHONE-16-PRO-256-BLU', '256GB / Blue', 1199, 14, [[$color, $blue], [$storage, $storage256]]);

        $galaxy = $this->product([
            'category_id' => $phones->id,
            'brand_id' => $samsung->id,
            'name' => 'Samsung Galaxy S26',
            'sku' => 'GALAXY-S26',
            'short_description' => 'A polished Android flagship with a bright display and all-day battery life.',
            'description' => 'A strong catalog sample for Android phone variants and product comparison.',
            'price' => 899,
            'compare_price' => 949,
            'rating_avg' => 4.6,
            'review_count' => 96,
            'sold_count' => 310,
        ]);
        $this->images($galaxy, 'https://placehold.co/900x900/dbeafe/1e3a8a?text=Galaxy+S26');
        $this->variant($galaxy, 'GALAXY-S26-128-BLK', '128GB / Black', 899, 25, [[$color, $black], [$storage, $storage128]]);
        $this->variant($galaxy, 'GALAXY-S26-256-WHT', '256GB / White', 999, 12, [[$color, $white], [$storage, $storage256]]);

        $macbook = $this->product([
            'category_id' => $laptops->id,
            'brand_id' => $apple->id,
            'name' => 'MacBook Air 14',
            'sku' => 'MACBOOK-AIR-14',
            'short_description' => 'Lightweight laptop for work, study, and daily productivity.',
            'description' => 'A simple laptop product with stock and pricing data for dashboard testing.',
            'price' => 1299,
            'compare_price' => 1399,
            'is_featured' => true,
            'rating_avg' => 4.7,
            'review_count' => 74,
            'sold_count' => 210,
        ]);
        $this->images($macbook, 'https://placehold.co/900x900/ecfeff/164e63?text=MacBook+Air+14');
        $this->variant($macbook, 'MACBOOK-AIR-14-256', '256GB / Silver', 1299, 10, [[$storage, $storage256]]);

        $shoe = $this->product([
            'category_id' => $shoes->id,
            'brand_id' => $nike->id,
            'name' => 'Nike Street Runner',
            'sku' => 'NIKE-STREET-RUNNER',
            'short_description' => 'Comfortable everyday sneakers for walking and casual outfits.',
            'description' => 'A fashion product sample with size variants.',
            'price' => 129,
            'compare_price' => 159,
            'rating_avg' => 4.5,
            'review_count' => 52,
            'sold_count' => 180,
        ]);
        $this->images($shoe, 'https://placehold.co/900x900/fef3c7/92400e?text=Nike+Runner');
        $this->variant($shoe, 'NIKE-STREET-RUNNER-42', 'Size 42 / Black', 129, 30, [[$color, $black], [$size, $size42]]);
        $this->variant($shoe, 'NIKE-STREET-RUNNER-43', 'Size 43 / Black', 129, 28, [[$color, $black], [$size, $size43]]);
    }

    private function category(string $name, int $sortOrder, ?int $parentId = null): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'parent_id' => $parentId,
                'name' => $name,
                'status' => 'active',
                'sort_order' => $sortOrder,
            ]
        );
    }

    private function brand(string $name): Brand
    {
        return Brand::query()->updateOrCreate(
            ['slug' => Str::slug($name)],
            [
                'name' => $name,
                'status' => 'active',
            ]
        );
    }

    private function attributeValue(Attribute $attribute, string $value): AttributeValue
    {
        return AttributeValue::query()->firstOrCreate([
            'attribute_id' => $attribute->id,
            'value' => $value,
        ]);
    }

    private function product(array $data): Product
    {
        return Product::query()->updateOrCreate(
            ['slug' => Str::slug($data['name'])],
            array_merge([
                'status' => 'active',
                'stock_status' => 'in_stock',
                'cost_price' => null,
                'is_featured' => false,
            ], $data)
        );
    }

    private function images(Product $product, string $primaryUrl): void
    {
        ProductImage::query()->updateOrCreate(
            ['product_id' => $product->id, 'sort_order' => 1],
            [
                'image_url' => $primaryUrl,
                'alt_text' => $product->name,
                'is_primary' => true,
            ]
        );
    }

    /**
     * @param  array<int, array{0: Attribute, 1: AttributeValue}>  $values
     */
    private function variant(Product $product, string $sku, string $name, float $price, int $stock, array $values): void
    {
        $variant = ProductVariant::query()->firstOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'name' => $name,
                'price' => $price,
                'compare_price' => null,
                'stock_quantity' => 0,
                'reserved_quantity' => 0,
                'sold_quantity' => 0,
                'status' => 'active',
            ]
        );

        if ($variant->wasRecentlyCreated && $stock > 0) {
            app(InventoryService::class)->adjust($variant->id, $stock, 'receive', 'Initial demo stock', 'initial-seed');
        }

        foreach ($values as [$attribute, $value]) {
            ProductVariantValue::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'attribute_id' => $attribute->id,
                ],
                ['attribute_value_id' => $value->id]
            );
        }
    }
}
