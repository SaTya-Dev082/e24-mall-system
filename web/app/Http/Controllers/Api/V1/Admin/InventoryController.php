<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustmentRequest;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'low_stock' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);
        $variants = ProductVariant::query()->with('product:id,name,slug')
            ->when($request->boolean('low_stock'), fn ($query) => $query->whereRaw('stock_quantity - reserved_quantity <= low_stock_threshold'))
            ->when(! empty($data['search']), fn ($query) => $query->where('sku', 'like', '%'.$data['search'].'%'))
            ->orderBy('id')->paginate($data['per_page'] ?? 20);
        $variants->getCollection()->transform(fn ($variant) => [
            'id' => $variant->id, 'sku' => $variant->sku, 'name' => $variant->name,
            'product' => $variant->product, 'status' => $variant->status,
            'stock_quantity' => $variant->stock_quantity, 'reserved_quantity' => $variant->reserved_quantity,
            'sold_quantity' => $variant->sold_quantity, 'available_stock' => $variant->available_stock,
            'low_stock_threshold' => $variant->low_stock_threshold,
            'is_low_stock' => $variant->available_stock <= $variant->low_stock_threshold,
        ]);

        return response()->json($variants);
    }

    public function movements(Request $request, int $variant): JsonResponse
    {
        $data = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100']]);
        ProductVariant::withTrashed()->findOrFail($variant);

        return response()->json(DB::table('stock_movements')->where('product_variant_id', $variant)->orderByDesc('id')->paginate($data['per_page'] ?? 20));
    }

    public function adjust(InventoryAdjustmentRequest $request, int $variant, InventoryService $inventory): JsonResponse
    {
        $data = $request->validated();
        $movement = $data['type'] === 'return'
            ? $inventory->returnStock($variant, $data['reference'], $data['quantity'], $data['idempotency_key'], $data['reason'], $request->user()->id)
            : $inventory->adjust($variant, $data['quantity'], $data['type'], $data['reason'], $data['idempotency_key'], $request->user()->id);

        return response()->json(['data' => $movement]);
    }
}
