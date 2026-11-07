<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductListResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', Rule::when($request->filled('min_price'), ['gte:min_price'])],
            'availability' => ['nullable', Rule::in(['in_stock', 'unavailable'])],
            'sort' => ['nullable', Rule::in(['newest', 'price_low', 'price_high', 'popular'])],
        ]);
        $perPage = $request->integer('limit', 20);

        $products = Product::query()
            ->select([
                'id',
                'category_id',
                'brand_id',
                'name',
                'slug',
                'price',
                'compare_price',
                'stock_status',
                'rating_avg',
                'review_count',
                'sold_count',
                'created_at',
            ])
            ->where('status', 'active')
            ->with(['category' => fn ($query) => $query->where('status', 'active'), 'brand' => fn ($query) => $query->where('status', 'active'), 'primaryImage'])
            ->withExists(['variants as has_available_stock' => fn ($query) => $query->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity')])
            ->when($request->filled('min_price'), fn ($query) => $query->where('price', '>=', $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price', '<=', $request->input('max_price')))
            ->when($request->filled('availability'), fn ($query) => $query->has('variants', $request->input('availability') === 'in_stock' ? '>=' : '<', 1, 'and', fn ($variants) => $variants->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity')))
            ->when($request->filled('category'), fn ($query) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('status', 'active')->where('slug', $request->string('category')->toString())
            ))
            ->when($request->filled('brand'), fn ($query) => $query->whereHas(
                'brand',
                fn ($brandQuery) => $brandQuery->where('status', 'active')->where('slug', $request->string('brand')->toString())
            ))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->whereLike('name', "%{$search}%")
                        ->orWhereLike('short_description', "%{$search}%");
                });
            })
            ->when(
                $request->string('sort')->toString() === 'price_low',
                fn ($query) => $query->orderBy('price')
            )
            ->when(
                $request->string('sort')->toString() === 'price_high',
                fn ($query) => $query->orderByDesc('price')
            )
            ->when(
                $request->string('sort')->toString() === 'popular',
                fn ($query) => $query->orderByDesc('sold_count')
            )
            ->when(
                ! in_array($request->string('sort')->toString(), ['price_low', 'price_high', 'popular'], true),
                fn ($query) => $query->latest()
            )
            ->orderByDesc('id')
            ->cursorPaginate($perPage);

        return ProductListResource::collection($products);
    }

    public function show(Product $product): ProductDetailResource
    {
        abort_unless($product->status === 'active', 404);

        $product->load(['category' => fn ($query) => $query->where('status', 'active'), 'brand' => fn ($query) => $query->where('status', 'active'), 'images', 'variants' => fn ($query) => $query->where('status', 'active')->with(['values.attribute', 'values.value'])]);

        return new ProductDetailResource($product);
    }
}
