<?php

namespace App\Http\Controllers;

use App\Services\ShopApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagementController extends Controller
{
    public const MODULES = ['catalog', 'inventory', 'orders', 'customers', 'payments', 'promotions', 'shipping', 'reviews', 'reports', 'administration'];

    private function page(string $view, array $data = []): View
    {
        $low = DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->whereNull('v.deleted_at')->whereNull('p.deleted_at')->where('v.status', 'active')->where('p.status', 'active')->whereRaw('v.stock_quantity - v.reserved_quantity <= v.low_stock_threshold');

        return view($view, $data + ['alertCount' => auth()->user()->canManage('inventory') ? (clone $low)->count() : 0, 'alerts' => auth()->user()->canManage('inventory') ? $low->select('v.id', 'v.sku', 'p.name')->limit(6)->get() : collect()]);
    }

    private function definition(string $resource): array
    {
        $def = config('admin_resources', [])[$resource] ?? null;
        abort_unless(is_array($def), 404);

        return $def;
    }

    public function index(Request $request, string $resource): View
    {
        $def = $this->definition($resource);
        $request->validate(['search' => 'nullable|string|max:100', 'archived' => 'nullable|in:1']);
        $query = DB::table($def['table']);
        if ($def['archive']) {
            $request->boolean('archived') ? $query->whereNotNull('deleted_at') : $query->whereNull('deleted_at');
        }
        if ($request->filled('search')) {
            $query->whereRaw('LOWER('.$def['name'].') LIKE ?', ['%'.strtolower($request->string('search')->toString()).'%']);
        }

        return $this->page($resource === 'banners' ? 'management.banners' : 'management.index', ['resource' => $resource, 'def' => $def, 'rows' => $resource === 'banners' ? $query->orderBy('sort_order')->orderBy('id')->paginate(20)->withQueryString() : $query->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function form(string $resource, ?int $id = null): View
    {
        $def = $this->definition($resource);
        abort_if(! $id && ($def['create'] ?? true) === false, 405);
        $item = $id ? DB::table($def['table'])->where('id', $id)->when($def['archive'], fn ($q) => $q->whereNull('deleted_at'))->first() : null;
        abort_if($id && ! $item, 404);
        $options = ['parent_id' => DB::table('categories')->whereNull('deleted_at')->when($resource === 'categories' && $id, fn ($q) => $q->where('id', '!=', $id))->pluck('name', 'id'), 'attribute_id' => DB::table('attributes')->whereNull('deleted_at')->pluck('name', 'id')];

        $options['shipping_method_id'] = DB::table('shipping_methods')->whereNull('deleted_at')->where('status', 'active')->pluck('name', 'id');

        return $this->page($resource === 'banners' ? 'management.banner-form' : 'management.form', compact('resource', 'def', 'item', 'options'));
    }

    public function save(Request $request, string $resource, ShopApi $api, ?int $id = null): RedirectResponse
    {
        $def = $this->definition($resource);
        $data = $request->only(array_keys($def['fields']));
        $api->send($id ? 'PATCH' : 'POST', 'admin/manage/'.$resource.($id ? '/'.$id : ''), $data);

        return redirect()->route('management.index', $resource)->with('status', 'Saved successfully.');
    }

    public function archive(string $resource, int $id, ShopApi $api): RedirectResponse
    {
        $this->definition($resource);
        $api->send('DELETE', 'admin/manage/'.$resource.'/'.$id);

        return back()->with('status', 'Archived successfully.');
    }

    public function restore(string $resource, int $id, ShopApi $api): RedirectResponse
    {
        $this->definition($resource);
        $api->send('PATCH', 'admin/manage/'.$resource.'/'.$id.'/restore');

        return back()->with('status', 'Restored successfully.');
    }

    private function product(int $product): object
    {
        $item = DB::table('products')->whereNull('deleted_at')->find($product);
        abort_unless($item, 404);

        return $item;
    }

    public function variants(int $product): View
    {
        return $this->page('management.variants', ['product' => $this->product($product), 'variants' => DB::table('product_variants')->where('product_id', $product)->whereNull('deleted_at')->orderBy('id')->get()]);
    }

    public function variantForm(int $product, ?int $variant = null): View
    {
        $item = $variant ? DB::table('product_variants')->where('product_id', $product)->whereNull('deleted_at')->find($variant) : null;
        abort_if($variant && ! $item, 404);

        return $this->page('management.variant-form', ['product' => $this->product($product), 'item' => $item, 'attributes' => DB::table('attributes')->whereNull('deleted_at')->orderBy('name')->get(), 'values' => DB::table('attribute_values')->whereNull('deleted_at')->get()->groupBy('attribute_id'), 'selected' => $variant ? DB::table('product_variant_values')->where('product_variant_id', $variant)->pluck('attribute_value_id', 'attribute_id') : collect()]);
    }

    public function saveVariant(Request $request, int $product, ShopApi $api, ?int $variant = null): RedirectResponse
    {
        $request->validate(['values' => 'nullable|array', 'values.*' => 'nullable|integer']);
        $data = $request->only(['sku', 'name', 'price', 'compare_price', 'status', 'low_stock_threshold']);
        if (! $variant) {
            $data['initial_stock'] = $request->input('initial_stock', 0);
        }$data['values'] = [];
        foreach ($request->input('values', []) as $attribute => $value) {
            if ($value) {
                $data['values'][] = ['attribute_id' => (int) $attribute, 'attribute_value_id' => (int) $value];
            }
        }
        $api->send($variant ? 'PATCH' : 'POST', 'admin/products/'.$product.'/variants'.($variant ? '/'.$variant : ''), $data);

        return redirect()->route('variants', $product)->with('status', 'Variant saved.');
    }

    public function images(int $product): View
    {
        return $this->page('management.images', ['product' => $this->product($product), 'images' => DB::table('product_images')->where('product_id', $product)->orderBy('sort_order')->get()]);
    }

    public function uploadImage(Request $request, int $product, ShopApi $api): RedirectResponse
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048', 'alt_text' => 'nullable|string|max:255']);
        $api->send('POST', 'admin/products/'.$product.'/images', $request->only('alt_text'), $request->file('image'));

        return back()->with('status', 'Image uploaded.');
    }

    public function primaryImage(int $product, int $image, ShopApi $api): RedirectResponse
    {
        $api->send('PATCH', 'admin/products/'.$product.'/images/'.$image);

        return back()->with('status', 'Primary image updated.');
    }

    public function removeImage(int $product, int $image, ShopApi $api): RedirectResponse
    {
        $api->send('DELETE', 'admin/products/'.$product.'/images/'.$image);

        return back()->with('status', 'Image removed.');
    }

    public function reservations(Request $request): View
    {
        $request->validate(['status' => 'nullable|in:pending,committed,released,expired']);
        $query = DB::table('inventory_reservations as r')->join('product_variants as v', 'v.id', '=', 'r.product_variant_id')->select('r.*', 'v.sku')->when($request->filled('status'), fn ($q) => $q->where('r.status', $request->input('status')));

        return $this->page('management.records', ['title' => 'Stock reservations', 'description' => 'Checkout holds and their lifecycle. Expired holds are released by the scheduler.', 'columns' => ['sku' => 'SKU', 'reference' => 'Reference', 'order_id' => 'Order ID', 'quantity' => 'Quantity', 'returned_quantity' => 'Returned', 'status' => 'Status', 'expires_at' => 'Expires (UTC)'], 'rows' => $query->orderByDesc('r.id')->paginate(20)->withQueryString()]);
    }

    public function threshold(Request $request, int $variant, ShopApi $api): RedirectResponse
    {
        $api->send('PATCH', 'admin/inventory/'.$variant.'/threshold', $request->only('low_stock_threshold'));

        return back()->with('status', 'Low-stock threshold updated.');
    }

    public function transition(Request $request, int $order, ShopApi $api): RedirectResponse
    {
        $api->send('PATCH', 'admin/orders/'.$order.'/transition', $request->only(['status', 'note', 'shipping_method_id', 'tracking_number', 'carrier_name']));

        return back()->with('status', 'Order updated. Paid cancellations require a separate refund request.');
    }

    public function customer(int $customer): View
    {
        $item = DB::table('users')->where('role', 'customer')->whereNull('deleted_at')->find($customer);
        abort_unless($item, 404);

        return $this->page('management.customer', ['item' => $item, 'orders' => DB::table('orders')->where('user_id', $customer)->orderByDesc('id')->paginate(15)]);
    }

    public function customerStatus(Request $request, int $customer, ShopApi $api): RedirectResponse
    {
        $api->send('PATCH', 'admin/customers/'.$customer.'/status', $request->only('status'));

        return back()->with('status', 'Customer status updated.');
    }

    public function payments(): View
    {
        return $this->page('management.records', ['title' => 'Payment records', 'description' => 'Recorded payment attempts and verified statuses. Refund approval does not execute a gateway refund.', 'columns' => ['order_id' => 'Order ID', 'payment_method' => 'Method', 'transaction_id' => 'Transaction', 'amount' => 'Amount', 'currency' => 'Currency', 'status' => 'Status', 'paid_at' => 'Paid at (UTC)'], 'rows' => DB::table('payments')->orderByDesc('id')->paginate(20)]);
    }

    public function refunds(): View
    {
        return $this->page('management.refunds', ['refunds' => DB::table('refund_requests as r')->join('orders as o', 'o.id', '=', 'r.order_id')->select('r.*', 'o.order_number')->orderByDesc('r.id')->paginate(20)]);
    }

    public function requestRefund(Request $request, ShopApi $api): RedirectResponse
    {
        $api->send('POST', 'admin/refunds', $request->only(['order_id', 'amount', 'reason']));

        return back()->with('status', 'Refund request recorded for review. No money has been moved.');
    }

    public function reviewRefund(Request $request, int $refund, ShopApi $api): RedirectResponse
    {
        $api->send('PATCH', 'admin/refunds/'.$refund, $request->only(['status', 'review_note']));

        return back()->with('status', 'Refund reviewed. Gateway execution is still required.');
    }

    public function staffList(): View
    {
        return $this->page('management.staff', ['staff' => DB::table('users')->where('role', 'admin')->whereNull('deleted_at')->select('id', 'name', 'email', 'status', 'admin_permissions')->orderBy('id')->paginate(20)]);
    }

    public function staffForm(?int $staff = null): View
    {
        $item = $staff ? DB::table('users')->where('role', 'admin')->whereNull('deleted_at')->find($staff) : null;
        abort_if($staff && ! $item, 404);

        return $this->page('management.staff-form', ['item' => $item, 'modules' => self::MODULES]);
    }

    public function saveStaff(Request $request, ShopApi $api, ?int $staff = null): RedirectResponse
    {
        $api->send($staff ? 'PATCH' : 'POST', 'admin/staff'.($staff ? '/'.$staff : ''), $request->only(['name', 'email', 'password', 'password_confirmation', 'status', 'admin_permissions']));

        return redirect()->route('staff')->with('status', 'Staff account saved.');
    }

    public function audit(): View
    {
        return $this->page('management.records', ['title' => 'Audit history', 'description' => 'Successful administrative changes. Passwords, tokens and uploaded content are never included.', 'columns' => ['id' => 'ID', 'user_id' => 'Actor ID', 'action' => 'Action', 'created_at' => 'Time (UTC)'], 'rows' => DB::table('activity_logs')->orderByDesc('id')->paginate(25)]);
    }

    public function storeSettings(): View
    {
        return $this->page('management.store-settings', ['item' => DB::table('store_settings')->find(1)]);
    }

    public function saveStoreSettings(Request $request, ShopApi $api): RedirectResponse
    {
        $api->send('PATCH', 'admin/store-settings', $request->only(['store_name', 'support_email', 'support_phone', 'currency']));

        return back()->with('status', 'Store settings saved.');
    }

    private function reportQuery(Request $request): array
    {
        $data = $request->validate(['type' => 'required|in:inventory,products,customers,payments,refunds', 'start' => 'nullable|date_format:Y-m-d', 'end' => 'nullable|date_format:Y-m-d|after_or_equal:start']);
        $module = match ($data['type']) {
            'products' => 'catalog','refunds' => 'payments',default => $data['type']
        };
        abort_unless(auth()->user()->canManage($module), 403);
        $start = Carbon::parse($data['start'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $end = Carbon::parse($data['end'] ?? now()->toDateString())->endOfDay();
        if ($start->gt($end) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['start' => ['Choose up to 366 days.']]);
        }
        $columns = match ($data['type']) {
            'inventory' => ['sku' => 'SKU', 'name' => 'Variant', 'stock_quantity' => 'On hand', 'reserved_quantity' => 'Reserved', 'sold_quantity' => 'Net sold', 'low_stock_threshold' => 'Low stock threshold'],
            'products' => ['name' => 'Product', 'sku' => 'SKU', 'price' => 'Base price (USD)', 'status' => 'Status', 'created_at' => 'Created (UTC)'],
            'customers' => ['name' => 'Name', 'created_at' => 'Joined (UTC)', 'status' => 'Status'],
            'payments' => ['order_id' => 'Order ID', 'payment_method' => 'Method', 'amount' => 'Amount', 'currency' => 'Currency', 'status' => 'Status', 'created_at' => 'Created (UTC)'],
            'refunds' => ['order_id' => 'Order ID', 'amount' => 'Requested amount', 'status' => 'Approval status', 'created_at' => 'Created (UTC)'],
        };
        $query = match ($data['type']) {
            'inventory' => DB::table('product_variants')->whereNull('deleted_at'),
            'products' => DB::table('products')->whereNull('deleted_at'),
            'customers' => DB::table('users')->where('role', 'customer')->whereNull('deleted_at')->whereBetween('created_at', [$start, $end]),
            'payments' => DB::table('payments')->whereBetween('created_at', [$start, $end]),
            'refunds' => DB::table('refund_requests')->whereBetween('created_at', [$start, $end]),
        };

        return [$data['type'], $query->select(array_merge(['id'], array_keys($columns)))->orderBy('id'), $columns, $start, $end];
    }

    public function report(Request $request): View
    {
        [$type,$query,$columns,$start,$end] = $this->reportQuery($request);

        return $this->page('management.report', compact('type', 'columns', 'start', 'end') + ['rows' => $query->paginate(20)->withQueryString()]);
    }

    public function exportReport(Request $request): StreamedResponse
    {
        [$type,$query,$columns] = $this->reportQuery($request);

        return response()->streamDownload(function () use ($query, $columns): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_values($columns), ',', '"', '');
            $query->chunkById(500, function ($rows) use ($out, $columns): void {
                foreach ($rows as $row) {
                    $cells = [];
                    foreach ($columns as $field => $label) {
                        $value = (string) ($row->$field ?? '');
                        $cells[] = preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                    }fputcsv($out, $cells, ',', '"', '');
                }
            });
            fclose($out);
        }, $type.'-report.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store']);
    }
}
