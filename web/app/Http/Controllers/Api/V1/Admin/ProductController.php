<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductListResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(['category', 'brand', 'primaryImage'])
            ->when($request->boolean('with_trashed'), fn ($query) => $query->withTrashed())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('brand_id'), fn ($query) => $query->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('sku', 'ILIKE', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ProductListResource::collection($products);
    }

    public function store(StoreProductRequest $request): ProductDetailResource
    {
        $product = DB::transaction(function () use ($request): Product {
            $data = $request->validated();
            $images = $data['images'] ?? [];
            $variants = $data['variants'] ?? [];
            unset($data['images'], $data['variants']);

            $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
            $data['is_featured'] = $data['is_featured'] ?? false;

            $product = Product::query()->create($data);
            $this->syncImages($product, $images);
            $this->syncVariants($product, $variants);

            return $product;
        });

        return new ProductDetailResource($product->refresh()->load(['category', 'brand', 'images', 'variants']));
    }

    public function show(Product $product): ProductDetailResource
    {
        return new ProductDetailResource($product->load(['category', 'brand', 'images', 'variants']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductDetailResource
    {
        $product = DB::transaction(function () use ($request, $product): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $data = $request->validated();
            $images = $data['images'] ?? null;
            $variants = $data['variants'] ?? null;
            unset($data['images'], $data['variants']);

            if (array_key_exists('name', $data) && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $product->update($data);

            if (is_array($images)) {
                $this->syncImages($product, $images);
            }

            if (is_array($variants)) {
                $this->syncVariants($product, $variants);
            }

            return $product;
        });

        return new ProductDetailResource($product->fresh()->load(['category', 'brand', 'images', 'variants']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->update(['status' => 'inactive']);
        $product->delete();

        return response()->json(status: 204);
    }

    public function restore(int $id): ProductDetailResource
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        return new ProductDetailResource($product->fresh()->load(['category', 'brand', 'images', 'variants']));
    }

    private function syncImages(Product $product, array $images): void
    {
        $keptIds = [];
        $hasPrimary = collect($images)->contains(fn ($image) => (bool) ($image['is_primary'] ?? false));

        foreach ($images as $index => $image) {
            if (! empty($image['id']) && ! $product->images()->whereKey($image['id'])->exists()) {
                throw ValidationException::withMessages(['images' => ['Image must belong to this product.']]);
            }
            $payload = [
                'image_url' => $image['image_url'],
                'alt_text' => $image['alt_text'] ?? $product->name,
                'is_primary' => $hasPrimary ? (bool) ($image['is_primary'] ?? false) : $index === 0,
                'sort_order' => $image['sort_order'] ?? $index + 1,
            ];

            $model = $product->images()->updateOrCreate(
                ['id' => $image['id'] ?? null],
                $payload
            );

            $keptIds[] = $model->id;
        }

        $product->images()->whereNotIn('id', $keptIds)->delete();
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $keptIds = [];

        foreach ($variants as $variant) {
            if (ProductVariant::withTrashed()->where('sku', $variant['sku'])->when(! empty($variant['id']), fn ($query) => $query->whereKeyNot($variant['id']))->exists()) {
                throw ValidationException::withMessages(['variants' => ['Variant SKU must be unique.']]);
            }
            $existing = ! empty($variant['id']) ? $product->variants()->lockForUpdate()->find($variant['id']) : null;
            if (! empty($variant['id'])) {
                if (! $existing || array_key_exists('stock_quantity', $variant)) {
                    throw ValidationException::withMessages(['variants' => ['Variant must belong to this product. Change existing stock through inventory adjustments.']]);
                }
                $existingSkuOwner = ProductVariant::withTrashed()
                    ->where('sku', $variant['sku'])
                    ->whereKeyNot($variant['id'])
                    ->exists();

                if ($existingSkuOwner) {
                    throw ValidationException::withMessages([
                        'variants' => ['Variant SKU must be unique for this product.'],
                    ]);
                }
            }

            $payload = [
                'sku' => $variant['sku'],
                'name' => $variant['name'],
                'price' => $variant['price'],
                'compare_price' => $variant['compare_price'] ?? null,
                'status' => $variant['status'],
            ];
            if (array_key_exists('low_stock_threshold', $variant)) {
                $payload['low_stock_threshold'] = $variant['low_stock_threshold'];
            }

            $model = $product->variants()->updateOrCreate(
                ['id' => $variant['id'] ?? null],
                $payload
            );

            if (! $existing && ($variant['stock_quantity'] ?? 0) > 0) {
                app(InventoryService::class)->adjust($model->id, $variant['stock_quantity'], 'receive', 'Initial stock', 'initial', request()->user()?->id);
            }

            $keptIds[] = $model->id;
        }

        $product->variants()->whereNotIn('id', $keptIds)->update(['status' => 'inactive']);
        if ($product->stock_status !== 'preorder') {
            $available = $product->variants()->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity')->exists();
            $product->update(['stock_status' => $available ? 'in_stock' : 'out_of_stock']);
        }
    }
}
