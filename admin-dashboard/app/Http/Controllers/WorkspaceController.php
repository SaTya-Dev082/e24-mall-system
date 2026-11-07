<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Requests\StockAdjustmentRequest;
use App\Services\ShopApi;
use App\Services\SystemNotifications;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceController extends Controller
{
    private function variants(): Builder
    {
        return DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereNull('v.deleted_at')->whereNull('p.deleted_at');
    }

    private function page(string $view, array $data = []): View
    {
        $low = $this->variants()->where('v.status', 'active')->where('p.status', 'active')->whereRaw('v.stock_quantity - v.reserved_quantity <= v.low_stock_threshold');

        return view($view, array_merge($data, [
            'alertCount' => auth()->user()->canManage('inventory') ? (clone $low)->count() : 0,
            'alerts' => auth()->user()->canManage('inventory') ? $low->select('v.id', 'v.sku', 'p.name')->orderBy('v.id')->limit(6)->get() : collect(),
        ]));
    }

    private function range(Request $request): array
    {
        $data = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'], 'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'group' => ['nullable', 'in:day,month'],
        ]);
        $start = Carbon::parse($data['start'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $end = Carbon::parse($data['end'] ?? now()->toDateString())->endOfDay();
        if ($start->gt($end) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['start' => ['Choose a date range of up to 366 days.']]);
        }

        return [$start, $end];
    }

    public function dashboard(Request $request): View
    {
        [$start, $end] = $this->range($request);
        $orders = DB::table('orders')->whereBetween('orders.created_at', [$start, $end]);
        $paid = (clone $orders)->where('payment_status', 'paid')->whereNotIn('order_status', ['cancelled', 'refunded']);
        $revenue = (clone $paid)->sum('total_amount');
        $daily = (clone $paid)->selectRaw('DATE(created_at) as day, SUM(total_amount) as total')->groupByRaw('DATE(created_at)')->orderBy('day')->get()->keyBy('day');
        $series = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $request->input('group') === 'month' ? $date->format('Y-m') : $date->toDateString();
            $series[$key] = ($series[$key] ?? 0) + (float) ($daily->get($date->toDateString())->total ?? 0);
        }
        $statuses = (clone $orders)->selectRaw('order_status as label, COUNT(*) as total')->groupBy('order_status')->orderByDesc('total')->get();
        $stock = $this->variants()->where('v.status', 'active')->where('p.status', 'active');
        $stockCounts = [
            'Healthy' => (clone $stock)->whereRaw('v.stock_quantity - v.reserved_quantity > v.low_stock_threshold')->count(),
            'Low stock' => (clone $stock)->whereRaw('v.stock_quantity - v.reserved_quantity > 0 AND v.stock_quantity - v.reserved_quantity <= v.low_stock_threshold')->count(),
            'Out of stock' => (clone $stock)->whereRaw('v.stock_quantity - v.reserved_quantity <= 0')->count(),
        ];
        $recentOrders = (clone $orders)->leftJoin('users as u', 'u.id', '=', 'orders.user_id')->select('orders.*', 'u.name as customer')->orderByDesc('orders.id')->limit(5)->get();
        $categories = DB::table('categories as c')->leftJoin('products as p', function ($join): void {
            $join->on('p.category_id', '=', 'c.id')->whereNull('p.deleted_at');
        })->whereNull('c.deleted_at')->select('c.name')->selectRaw('COUNT(p.id) as total')->groupBy('c.id', 'c.name')->orderByDesc('total')->limit(5)->get();
        $activity = DB::table('stock_movements as m')->join('product_variants as v', 'v.id', '=', 'm.product_variant_id')->select('m.*', 'v.sku')->orderByDesc('m.id')->limit(5)->get();

        return $this->page('dashboard', compact('start', 'end', 'revenue', 'series', 'statuses', 'stockCounts', 'recentOrders', 'categories', 'activity') + [
            'orderCount' => (clone $orders)->count(),
            'customerCount' => DB::table('users')->where('role', 'customer')->whereNull('deleted_at')->whereBetween('created_at', [$start, $end])->count(),
            'productCount' => DB::table('products')->whereNull('deleted_at')->count(),
            'availableStock' => (int) (clone $stock)->sum(DB::raw('v.stock_quantity - v.reserved_quantity')),
        ]);
    }

    public function inventory(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'stock' => ['nullable', 'in:low,out,healthy'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = $this->variants()->select('v.*', 'p.name as product_name');
        if (! empty($data['stock'])) {
            $query->where('v.status', 'active')->where('p.status', 'active');
        }
        if (! empty($data['search'])) {
            $term = '%'.strtolower($data['search']).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(v.sku) LIKE ?', [$term])->orWhereRaw('LOWER(p.name) LIKE ?', [$term]));
        }
        if (($data['stock'] ?? null) === 'low') {
            $query->whereRaw('v.stock_quantity - v.reserved_quantity <= v.low_stock_threshold');
        } elseif (($data['stock'] ?? null) === 'out') {
            $query->whereRaw('v.stock_quantity - v.reserved_quantity <= 0');
        } elseif (($data['stock'] ?? null) === 'healthy') {
            $query->whereRaw('v.stock_quantity - v.reserved_quantity > v.low_stock_threshold');
        }

        return $this->page('workspace.inventory', ['variants' => $query->orderBy('v.id')->paginate(15)->withQueryString()]);
    }

    public function history(int $variant): View
    {
        $item = DB::table('product_variants')->where('id', $variant)->first();
        abort_unless($item, 404);
        $movements = DB::table('stock_movements as m')->leftJoin('users as u', 'u.id', '=', 'm.actor_id')->where('m.product_variant_id', $variant)->select('m.*', 'u.name as actor')->orderByDesc('m.id')->paginate(20);

        return $this->page('workspace.history', compact('item', 'movements'));
    }

    public function adjust(StockAdjustmentRequest $request, int $variant, ShopApi $api): RedirectResponse
    {
        $api->send('POST', 'admin/inventory/'.$variant.'/adjustments', $request->validated());

        return redirect()->route('inventory.history', $variant)->with('status', 'Stock updated successfully.');
    }

    public function products(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'archived' => ['nullable', 'in:1'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')->select('p.*', 'c.name as category_name');
        $request->boolean('archived') ? $query->whereNotNull('p.deleted_at') : $query->whereNull('p.deleted_at');
        if (! empty($data['search'])) {
            $term = '%'.strtolower($data['search']).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(p.name) LIKE ?', [$term])->orWhereRaw('LOWER(p.sku) LIKE ?', [$term]));
        }

        return $this->page('workspace.products', ['products' => $query->orderByDesc('p.id')->paginate(15)->withQueryString()]);
    }

    public function productForm(?int $product = null): View
    {
        $item = $product ? DB::table('products')->whereNull('deleted_at')->find($product) : null;
        abort_if($product && ! $item, 404);

        return $this->page('workspace.product-form', [
            'item' => $item, 'categories' => DB::table('categories')->whereNull('deleted_at')->orderBy('name')->get(),
            'brands' => DB::table('brands')->whereNull('deleted_at')->orderBy('name')->get(),
        ]);
    }

    public function saveProduct(ProductRequest $request, ShopApi $api, ?int $product = null): RedirectResponse
    {
        $data = $request->safe()->except('initial_stock');
        if ($product) {
            $existing = DB::table('products')->whereNull('deleted_at')->find($product);
            abort_unless($existing, 404);
            $data['slug'] = $existing->slug;
        }
        if (! $product) {
            $data['slug'] = Str::substr(Str::slug($data['name']) ?: 'product', 0, 230).'-'.strtolower(Str::random(8));
            $data['stock_status'] = 'out_of_stock';
            $data['variants'] = [[
                'sku' => Str::substr($data['sku'] ?? 'SKU', 0, 80).'-DEFAULT-'.Str::upper(Str::random(6)),
                'name' => 'Default', 'price' => $data['price'], 'stock_quantity' => $request->integer('initial_stock'), 'status' => 'active',
            ]];
        }
        $api->send($product ? 'PATCH' : 'POST', 'admin/products'.($product ? '/'.$product : ''), $data);

        return redirect()->route('products')->with('status', $product ? 'Product updated.' : 'Product created. Manage its stock in Inventory.');
    }

    public function archiveProduct(int $product, ShopApi $api): RedirectResponse
    {
        $api->send('DELETE', 'admin/products/'.$product);

        return redirect()->route('products')->with('status', 'Product archived. You can restore it from Archived products.');
    }

    public function restoreProduct(int $product, ShopApi $api): RedirectResponse
    {
        $api->send('PATCH', 'admin/products/'.$product.'/restore');

        return redirect()->route('products')->with('status', 'Product restored as inactive. Edit it to publish.');
    }

    public function orders(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,cancelled,refunded']]);
        $orders = DB::table('orders as o')->leftJoin('users as u', 'u.id', '=', 'o.user_id')->select('o.*', 'u.name as customer')
            ->when(! empty($data['search']), fn ($q) => $q->where('o.order_number', 'like', '%'.$data['search'].'%'))
            ->when(! empty($data['status']), fn ($q) => $q->where('o.order_status', $data['status']))->orderByDesc('o.id')->paginate(20)->withQueryString();

        return $this->page('workspace.orders', compact('orders'));
    }

    public function order(int $order): View
    {
        $item = DB::table('orders')->find($order);
        abort_unless($item, 404);

        return $this->page('workspace.order', ['item' => $item, 'items' => DB::table('order_items')->where('order_id', $order)->get(), 'history' => DB::table('order_status_histories')->where('order_id', $order)->orderByDesc('id')->get(), 'shippingMethods' => DB::table('shipping_methods')->whereNull('deleted_at')->where('status', 'active')->get(), 'shipments' => DB::table('shipments')->where('order_id', $order)->get()]);
    }

    public function customers(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $query = DB::table('users')->where('role', 'customer')->whereNull('deleted_at')->select('id', 'name', 'email', 'phone', 'status', 'created_at');
        if (! empty($data['search'])) {
            $term = '%'.strtolower($data['search']).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term]));
        }

        return $this->page('workspace.customers', ['customers' => $query->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function reports(Request $request): View
    {
        if ($request->filled('type')) {
            return app(ManagementController::class)->report($request);
        }
        [$start, $end] = $this->range($request);
        $query = DB::table('orders')->whereBetween('created_at', [$start, $end]);

        return $this->page('workspace.reports', compact('start', 'end') + ['count' => (clone $query)->count(), 'paid' => (clone $query)->where('payment_status', 'paid')->whereNotIn('order_status', ['cancelled', 'refunded'])->sum('total_amount')]);
    }

    public function export(Request $request): StreamedResponse
    {
        if ($request->filled('type')) {
            return app(ManagementController::class)->exportReport($request);
        }
        [$start, $end] = $this->range($request);

        return response()->streamDownload(function () use ($start, $end): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Order', 'Date (UTC)', 'Total', 'Payment status', 'Order status'], ',', '"', '');
            DB::table('orders')->whereBetween('created_at', [$start, $end])->orderBy('id')->chunkById(500, function ($rows) use ($stream): void {
                foreach ($rows as $row) {
                    $cells = [$row->order_number, $row->created_at, $row->total_amount, $row->payment_status, $row->order_status];
                    fputcsv($stream, array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $cells), ',', '"', '');
                }
            });
            fclose($stream);
        }, 'orders-'.$start->format('Y-m-d').'-'.$end->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store']);
    }

    public function settings(): View
    {
        return $this->page('workspace.settings');
    }

    public function appearance(): View
    {
        return $this->page('workspace.appearance');
    }

    public function notifications(Request $request, SystemNotifications $notifications): View
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(['all', 'catalog', 'inventory', 'orders', 'payments', 'reviews', 'activity'])], 'page' => 'nullable|integer|min:1']);
        $type = $data['type'] ?? 'all';
        $categories = $notifications->categories();
        abort_unless(isset($categories[$type]), 403);
        $items = $notifications->query($type)->paginate(20)->withQueryString();
        $items->setCollection($notifications->decorate($items->getCollection()));

        return $this->page('workspace.notifications', ['items' => $items, 'type' => $type, 'categories' => $categories, 'notificationSummary' => $notifications->summary()]);
    }
}
