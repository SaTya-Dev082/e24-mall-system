<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagementController extends Controller
{
    public const MODULES = ['catalog', 'inventory', 'orders', 'customers', 'payments', 'promotions', 'shipping', 'reviews', 'reports', 'administration'];

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operation' => [$message]]);
        }
    }

    private function definition(string $resource): array
    {
        $definition = config('admin_resources', [])[$resource] ?? null;
        abort_unless(is_array($definition), 404);

        return $definition;
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        return $this->save($request, $resource, null);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        return $this->save($request, $resource, $id);
    }

    private function save(Request $request, string $resource, ?int $id): JsonResponse
    {
        $def = $this->definition($resource);
        abort_if(! $id && ($def['create'] ?? true) === false, 405);

        return DB::transaction(function () use ($request, $resource, $id, $def): JsonResponse {
            $table = $def['table'];
            if ($resource === 'categories') {
                DB::table('categories')->orderBy('id')->lockForUpdate()->get();
            }
            $existing = $id ? DB::table($table)->where('id', $id)->when($def['archive'], fn ($q) => $q->whereNull('deleted_at'))->lockForUpdate()->first() : null;
            abort_if($id && ! $existing, 404);
            $rules = $def['fields'];
            if (in_array($resource, ['categories', 'brands'], true)) {
                $slug = $request->input('slug') ?: ($existing->slug ?? Str::substr(Str::slug($request->string('name')->toString()) ?: 'item', 0, 220).'-'.Str::lower(Str::random(8)));
                $request->merge(['slug' => $slug]);
                $rules['slug'] = ['required', 'string', 'max:255', Rule::unique($table, 'slug')->ignore($id)];
            }
            if ($resource === 'coupons') {
                $request->merge(['code' => Str::upper(trim($request->string('code')->toString()))]);
                $rules['code'] = ['required', 'string', 'max:100', Rule::unique('coupons', 'code')->ignore($id)];
            }
            if ($resource === 'categories') {
                $rules['parent_id'] = ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')];
            }
            if ($resource === 'attribute-values') {
                $rules['attribute_id'] = ['required', 'integer', Rule::exists('attributes', 'id')->whereNull('deleted_at')];
                $rules['value'] = ['required', 'string', 'max:100', Rule::unique('attribute_values', 'value')->where('attribute_id', $request->integer('attribute_id'))->ignore($id)];
            }
            if ($resource === 'shipping-zones') {
                $rules['shipping_method_id'] = ['required', 'integer', Rule::exists('shipping_methods', 'id')->whereNull('deleted_at')->where('status', 'active')];
            }
            $data = $request->validate($rules);
            if ($resource === 'categories') {
                $parent = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
                $visited = [];
                while ($parent) {
                    $this->require($parent !== $id && ! in_array($parent, $visited, true), 'A category cannot be its own ancestor.');
                    $visited[] = $parent;
                    $parent = (int) DB::table('categories')->where('id', $parent)->value('parent_id');
                }
            }
            if ($resource === 'attribute-values' && $existing && $existing->attribute_id != $data['attribute_id']) {
                $this->require(! DB::table('product_variant_values')->where('attribute_value_id', $id)->exists(), 'An assigned value cannot be moved to another attribute.');
            }
            if ($resource === 'coupons' && $data['type'] === 'percentage') {
                $this->require($data['value'] <= 100, 'Percentage discounts cannot exceed 100.');
            }
            $data['updated_at'] = now();
            if ($id) {
                DB::table($table)->where('id', $id)->update($data);
            } else {
                $data['created_at'] = now();
                $id = DB::table($table)->insertGetId($data);
            }

            return response()->json(['data' => DB::table($table)->find($id)], $existing ? 200 : 201);
        }, 5);
    }

    public function archive(string $resource, int $id): JsonResponse
    {
        $def = $this->definition($resource);
        abort_unless($def['archive'], 405);

        return DB::transaction(function () use ($resource, $id, $def): JsonResponse {
            $item = DB::table($def['table'])->where('id', $id)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($item, 404);
            if ($resource === 'categories') {
                $this->require(! DB::table('categories')->where('parent_id', $id)->whereNull('deleted_at')->exists() && ! DB::table('products')->where('category_id', $id)->exists(), 'Move child categories and products before archiving this category.');
            }
            if ($resource === 'brands') {
                $this->require(! DB::table('products')->where('brand_id', $id)->exists(), 'Move products to another brand before archiving.');
            }
            if ($resource === 'attributes') {
                $this->require(! DB::table('product_variant_values')->where('attribute_id', $id)->exists(), 'This attribute is assigned to variants.');
            }
            if ($resource === 'attribute-values') {
                $this->require(! DB::table('product_variant_values')->where('attribute_value_id', $id)->exists(), 'This value is assigned to variants.');
            }
            DB::table($def['table'])->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);

            return response()->json(['message' => 'Archived.']);
        }, 5);
    }

    public function restore(string $resource, int $id): JsonResponse
    {
        $def = $this->definition($resource);
        abort_unless($def['archive'], 405);
        $item = DB::table($def['table'])->find($id);
        abort_unless($item, 404);
        if ($resource === 'categories' && $item->parent_id) {
            $this->require(DB::table('categories')->where('id', $item->parent_id)->whereNull('deleted_at')->exists(), 'Restore the parent category first.');
        }
        if ($resource === 'attribute-values') {
            $this->require(DB::table('attributes')->where('id', $item->attribute_id)->whereNull('deleted_at')->exists(), 'Restore the attribute first.');
        }
        DB::table($def['table'])->where('id', $id)->update(['deleted_at' => null, 'updated_at' => now()]);

        return response()->json(['message' => 'Restored.']);
    }

    public function saveVariant(Request $request, int $product, ?int $variant = null): JsonResponse
    {
        return DB::transaction(function () use ($request, $product, $variant): JsonResponse {
            $parent = Product::query()->lockForUpdate()->findOrFail($product);
            $item = $variant ? $parent->variants()->lockForUpdate()->findOrFail($variant) : new ProductVariant(['product_id' => $product]);
            $data = $request->validate([
                'sku' => ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variant)], 'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0|max:9999999999.99', 'compare_price' => 'nullable|numeric|min:0|max:9999999999.99',
                'status' => 'required|in:active,inactive', 'low_stock_threshold' => 'required|integer|between:0,1000000000',
                'initial_stock' => $variant ? ['prohibited'] : ['nullable', 'integer', 'between:0,1000000000'],
                'values' => 'sometimes|array|max:30', 'values.*.attribute_id' => 'required|integer|distinct', 'values.*.attribute_value_id' => 'required|integer',
                'stock_quantity' => 'prohibited', 'reserved_quantity' => 'prohibited', 'sold_quantity' => 'prohibited',
            ]);
            foreach ($data['values'] ?? [] as $value) {
                $this->require(DB::table('attribute_values as av')->join('attributes as a', 'a.id', '=', 'av.attribute_id')->where('av.id', $value['attribute_value_id'])->where('av.attribute_id', $value['attribute_id'])->whereNull('av.deleted_at')->whereNull('a.deleted_at')->exists(), 'Attribute value does not belong to the selected attribute.');
            }
            $item->fill(collect($data)->except(['initial_stock', 'values'])->all());
            $item->save();
            if (array_key_exists('values', $data)) {
                DB::table('product_variant_values')->where('product_variant_id', $item->id)->delete();
                foreach ($data['values'] as $value) {
                    DB::table('product_variant_values')->insert($value + ['product_variant_id' => $item->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            if (! $variant && ($data['initial_stock'] ?? 0) > 0) {
                app(InventoryService::class)->adjust($item->id, $data['initial_stock'], 'receive', 'Initial variant stock', 'initial', $request->user()->id);
            }
            if ($parent->stock_status !== 'preorder') {
                $parent->update(['stock_status' => $parent->variants()->where('status', 'active')->whereColumn('stock_quantity', '>', 'reserved_quantity')->exists() ? 'in_stock' : 'out_of_stock']);
            }

            return response()->json(['data' => $item->fresh()], $variant ? 200 : 201);
        }, 5);
    }

    public function uploadImage(Request $request, int $product): JsonResponse
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=6000,max_height=6000', 'alt_text' => 'nullable|string|max:255']);
        $path = $request->file('image')->store('products/'.$product, 'public');
        try {
            return DB::transaction(function () use ($request, $product, $path): JsonResponse {
                $parent = Product::query()->lockForUpdate()->findOrFail($product);
                $this->require($parent->images()->count() < 20, 'A product can have at most 20 images.');
                $image = $parent->images()->create(['image_url' => url(Storage::disk('public')->url($path)), 'alt_text' => $request->input('alt_text') ?: $parent->name, 'is_primary' => ! $parent->images()->exists(), 'sort_order' => $parent->images()->count() + 1]);

                return response()->json(['data' => $image], 201);
            }, 5);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
    }

    public function primaryImage(int $product, int $image): JsonResponse
    {
        return DB::transaction(function () use ($product, $image): JsonResponse {
            $parent = Product::query()->lockForUpdate()->findOrFail($product);
            $item = $parent->images()->findOrFail($image);
            $parent->images()->update(['is_primary' => false]);
            $item->update(['is_primary' => true]);

            return response()->json(['message' => 'Primary image updated.']);
        }, 5);
    }

    public function removeImage(int $product, int $image): JsonResponse
    {
        return DB::transaction(function () use ($product, $image): JsonResponse {
            $parent = Product::query()->lockForUpdate()->findOrFail($product);
            $item = $parent->images()->findOrFail($image);
            $primary = $item->is_primary;
            $item->delete();
            if ($primary) {
                $parent->images()->orderBy('id')->first()?->update(['is_primary' => true]);
            }

            return response()->json(['message' => 'Image removed from product.']);
        }, 5);
    }

    public function transition(Request $request, int $order): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:confirmed,processing,shipped,delivered,cancelled', 'note' => 'required|string|max:2000', 'shipping_method_id' => 'nullable|integer', 'tracking_number' => 'nullable|string|max:255', 'carrier_name' => 'nullable|string|max:255']);

        return DB::transaction(function () use ($request, $order, $data): JsonResponse {
            $item = DB::table('orders')->where('id', $order)->lockForUpdate()->first();
            abort_unless($item, 404);
            if ($item->order_status === $data['status']) {
                return response()->json(['message' => 'Order already has this status.']);
            }
            $allowed = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['processing', 'cancelled'], 'processing' => ['shipped', 'cancelled'], 'shipped' => ['delivered']];
            $this->require(in_array($data['status'], $allowed[$item->order_status] ?? [], true), 'Invalid order status transition.');
            $inventory = app(InventoryService::class);
            if (in_array($data['status'], ['confirmed', 'processing', 'shipped'], true)) {
                $ready = $item->payment_status === 'paid' || DB::table('payments')->where('order_id', $order)->where('payment_method', 'cash_on_delivery')->where('status', 'pending')->exists();
                $this->require($ready, 'Fulfillment requires verified payment or an existing cash-on-delivery payment record.');
            }
            if ($data['status'] === 'confirmed') {
                $lines = DB::table('order_items')->where('order_id', $order)->orderBy('product_id')->orderBy('product_variant_id')->get();
                $this->require($lines->isNotEmpty(), 'An empty order cannot be confirmed.');
                foreach ($lines->groupBy('product_variant_id') as $variant => $items) {
                    $this->require((bool) $variant, 'Order items require a valid inventory variant.');
                    $quantity = (int) $items->sum('quantity');
                    $reservation = DB::table('inventory_reservations')->where('order_id', $order)->where('product_variant_id', $variant)->first();
                    if (! $reservation) {
                        $reference = 'order-'.$order.'-'.Str::uuid();
                        $reservation = $inventory->reserve((int) $variant, $quantity, $reference);
                        DB::table('inventory_reservations')->where('id', $reservation->id)->update(['order_id' => $order]);
                    }
                    $this->require((int) $reservation->quantity === $quantity, 'Reserved quantity does not match the order.');
                    $inventory->commit((int) $variant, $reservation->reference);
                }
            }
            if ($data['status'] === 'cancelled') {
                $reservations = DB::table('inventory_reservations')->where('order_id', $order)->orderBy('product_variant_id')->get();
                if ($item->order_status !== 'pending') {
                    $this->require($reservations->isNotEmpty(), 'Inventory links are missing; reconcile this order before cancellation.');
                }
                foreach ($reservations as $reservation) {
                    if ($reservation->status === 'pending') {
                        $inventory->release($reservation->product_variant_id, $reservation->reference);
                    } elseif ($reservation->status === 'committed' && $reservation->returned_quantity < $reservation->quantity) {
                        $inventory->returnStock($reservation->product_variant_id, $reservation->reference, $reservation->quantity - $reservation->returned_quantity, 'cancel-order-'.$order.'-'.$reservation->id, 'Order cancellation: '.$data['note'], $request->user()->id);
                    }
                }
            }
            if ($data['status'] === 'shipped') {
                $this->require(! empty($data['tracking_number']) && ! empty($data['carrier_name']) && ! empty($data['shipping_method_id']), 'Shipping method, carrier and tracking number are required.');
                $this->require(DB::table('shipping_methods')->where('id', $data['shipping_method_id'])->where('status', 'active')->whereNull('deleted_at')->exists(), 'Choose an active shipping method.');
                DB::table('shipments')->insert(['order_id' => $order, 'shipping_method_id' => $data['shipping_method_id'], 'tracking_number' => $data['tracking_number'], 'carrier_name' => $data['carrier_name'], 'status' => 'shipped', 'shipped_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($data['status'] === 'delivered') {
                DB::table('shipments')->where('order_id', $order)->whereIn('status', ['shipped', 'in_transit'])->update(['status' => 'delivered', 'delivered_at' => now(), 'updated_at' => now()]);
            }
            DB::table('orders')->where('id', $order)->update(['order_status' => $data['status'], 'updated_at' => now()]);
            DB::table('order_status_histories')->insert(['order_id' => $order, 'from_status' => $item->order_status, 'to_status' => $data['status'], 'note' => $data['note'], 'changed_by' => $request->user()->id, 'created_at' => now()]);

            return response()->json(['message' => 'Order updated. Paid cancellations require a separate refund request.']);
        }, 5);
    }

    public function customerStatus(Request $request, int $customer): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:active,inactive,blocked']);

        return DB::transaction(function () use ($customer, $data): JsonResponse {
            $user = User::query()->where('role', 'customer')->whereNull('deleted_at')->lockForUpdate()->findOrFail($customer);
            $user->update($data);
            if ($data['status'] !== 'active') {
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $customer)->delete();
            }

            return response()->json(['message' => 'Customer account updated.']);
        }, 5);
    }

    public function requestRefund(Request $request): JsonResponse
    {
        $data = $request->validate(['order_id' => 'required|integer', 'amount' => 'required|numeric|min:0.01|max:9999999999.99', 'reason' => 'required|string|max:2000']);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $order = DB::table('orders')->where('id', $data['order_id'])->lockForUpdate()->first();
            abort_unless($order, 404);
            $this->require($order->payment_status === 'paid', 'Only a paid order can have a refund request.');
            $reserved = DB::table('refund_requests')->where('order_id', $order->id)->whereIn('status', ['requested', 'approved'])->sum('amount');
            $this->require(round(($reserved + $data['amount']) * 100) <= round($order->total_amount * 100), 'Refund requests exceed the paid order amount.');
            $id = DB::table('refund_requests')->insertGetId($data + ['requested_by' => $request->user()->id, 'status' => 'requested', 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => ['id' => $id]], 201);
        }, 5);
    }

    public function reviewRefund(Request $request, int $refund): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'review_note' => 'required|string|max:2000']);

        return DB::transaction(function () use ($request, $refund, $data): JsonResponse {
            $item = DB::table('refund_requests')->where('id', $refund)->lockForUpdate()->first();
            abort_unless($item, 404);
            $this->require($item->status === 'requested', 'This request has already been reviewed.');
            DB::table('refund_requests')->where('id', $refund)->update($data + ['reviewed_by' => $request->user()->id, 'updated_at' => now()]);

            return response()->json(['message' => 'Refund request reviewed. No money has been moved; payment-provider execution is required.']);
        }, 5);
    }

    public function staff(Request $request, ?int $staff = null): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff)], 'password' => $staff ? 'nullable|string|min:12|max:128|confirmed' : 'required|string|min:12|max:128|confirmed', 'status' => 'required|in:active,inactive,blocked', 'admin_permissions' => 'required|array|min:1', 'admin_permissions.*' => ['required', Rule::in(self::MODULES)]]);

        return DB::transaction(function () use ($request, $staff, $data): JsonResponse {
            $admins = User::query()->where('role', 'admin')->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get();
            $user = $staff ? $admins->firstWhere('id', $staff) : new User;
            abort_if($staff && ! $user, 404);
            if ($staff) {
                $this->require($staff !== $request->user()->id, 'You cannot change your own access through staff management.');
            }
            $otherOwners = $admins->filter(fn ($admin) => $admin->id !== $staff && $admin->canManage('administration'))->count();
            $willOwn = $data['status'] === 'active' && in_array('administration', $data['admin_permissions'], true);
            $this->require($otherOwners > 0 || $willOwn, 'At least one active administrator must retain administration permission.');
            if (empty($data['password'])) {
                unset($data['password']);
            }$user->forceFill($data + ['role' => 'admin']);
            $user->save();
            $user->tokens()->delete();
            if ($user->status !== 'active') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            return response()->json(['message' => 'Staff account saved.'], $staff ? 200 : 201);
        }, 5);
    }

    public function threshold(Request $request, int $variant): JsonResponse
    {
        $data = $request->validate(['low_stock_threshold' => 'required|integer|between:0,1000000000']);

        return DB::transaction(function () use ($variant, $data): JsonResponse {
            $item = ProductVariant::query()->findOrFail($variant);
            Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            $item = ProductVariant::query()->lockForUpdate()->findOrFail($variant);
            $item->update($data);

            return response()->json(['message' => 'Low-stock threshold updated.']);
        }, 5);
    }

    public function storeSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['store_name' => 'required|string|max:255', 'support_email' => 'nullable|email|max:255', 'support_phone' => 'nullable|string|max:50', 'currency' => 'required|in:USD']);
        DB::table('store_settings')->where('id', 1)->update($data + ['updated_at' => now()]);

        return response()->json(['message' => 'Store settings saved.']);
    }
}
