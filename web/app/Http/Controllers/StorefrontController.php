<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    private function products(): Builder
    {
        return Product::query()->where('status', 'active');
    }

    private function cards(): Builder
    {
        return $this->products()->select(['id', 'name', 'slug', 'price', 'stock_status', 'created_at'])->with(['primaryImage:id,product_id,image_url,alt_text'])->withExists(['variants as has_available_stock' => fn ($query) => $query->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity')]);
    }

    private function categories(): Collection
    {
        return Category::query()->where('status', 'active')->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'slug']);
    }

    public function home(): View
    {
        return view('pages.home', ['banners' => DB::table('storefront_banners')->whereNull('deleted_at')->where('status', 'active')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', now()))->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>', now()))->orderBy('sort_order')->orderBy('id')->limit(8)->get(), 'categories' => $this->categories(), 'featured' => $this->cards()->where('is_featured', true)->orderByDesc('created_at')->orderByDesc('id')->limit(4)->get(), 'latest' => $this->cards()->orderByDesc('created_at')->orderByDesc('id')->limit(8)->get()]);
    }

    public function index(CatalogRequest $request): View
    {
        $filters = $request->validated();
        $query = $this->cards();
        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', '%'.$search.'%')->orWhereLike('short_description', '%'.$search.'%');
            });
        }
        foreach (['category', 'brand'] as $relation) {
            if ($slug = $filters[$relation] ?? null) {
                $query->whereHas($relation, fn ($query) => $query->where('status', 'active')->where('slug', $slug));
            }
        }
        if ($availability = $filters['availability'] ?? null) {
            $query->has('variants', $availability === 'in_stock' ? '>=' : '<', 1, 'and', fn (Builder $query) => $query->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity'));
        }
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        match ($filters['sort'] ?? 'newest') {
            'price_low' => $query->orderBy('price'),'price_high' => $query->orderByDesc('price'),default => $query->orderByDesc('created_at')
        };

        return view('pages.products.index', ['categories' => $this->categories(), 'brands' => Brand::query()->where('status', 'active')->orderBy('name')->orderBy('id')->get(['name', 'slug']), 'filters' => $filters, 'products' => $query->orderByDesc('id')->paginate(12)->appends($filters)]);
    }

    public function show(string $slug): View
    {
        $product = $this->products()->where('slug', $slug)->with(['category' => fn ($query) => $query->where('status', 'active'), 'brand' => fn ($query) => $query->where('status', 'active'), 'images', 'variants' => fn ($query) => $query->orderBy('id')->with(['values.attribute', 'values.value'])])->firstOrFail();

        return view('pages.products.show', ['categories' => $this->categories(), 'product' => $product, 'related' => $this->cards()->where('category_id', $product->category_id)->whereKeyNot($product->id)->orderByDesc('created_at')->orderByDesc('id')->limit(4)->get()]);
    }
}
