<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SystemNotifications
{
    public function categories(): array
    {
        $categories = ['all' => 'All alerts'];
        foreach (['catalog' => 'Catalog', 'inventory' => 'Inventory', 'orders' => 'Orders', 'payments' => 'Payments', 'reviews' => 'Reviews'] as $module => $label) {
            if (auth()->user()->canManage($module)) {
                $categories[$module] = $label;
            }
        }
        if (auth()->user()->canManage('administration')) {
            $categories['activity'] = 'System activity';
        }

        return $categories;
    }

    private function sources(): array
    {
        $sources = [];
        if (auth()->user()->canManage('catalog')) {
            $sources[] = ['kind' => 'draft-product', 'category' => 'catalog', 'title' => 'Draft product', 'level' => 'info', 'icon' => 'box', 'entity' => 'id', 'time' => 'COALESCE(updated_at, created_at)', 'message' => 'name', 'query' => DB::table('products')->whereNull('deleted_at')->where('status', 'draft')];
        }
        if (auth()->user()->canManage('inventory')) {
            $sources[] = ['kind' => 'low-stock', 'category' => 'inventory', 'title' => 'Stock needs attention', 'level' => 'warning', 'icon' => 'layers', 'entity' => 'v.id', 'time' => 'COALESCE(v.updated_at, v.created_at)', 'message' => "p.name || ' · ' || v.sku", 'query' => DB::table('product_variants as v')->join('products as p', 'p.id', '=', 'v.product_id')->whereNull('v.deleted_at')->whereNull('p.deleted_at')->where('v.status', 'active')->where('p.status', 'active')->whereRaw('v.stock_quantity - v.reserved_quantity <= v.low_stock_threshold')];
            $sources[] = ['kind' => 'expired-reservation', 'category' => 'inventory', 'title' => 'Expired hold awaiting release', 'level' => 'error', 'icon' => 'calendar', 'entity' => 'id', 'time' => 'expires_at', 'message' => 'reference', 'query' => DB::table('inventory_reservations')->where('status', 'pending')->where('expires_at', '<=', now())];
        }
        if (auth()->user()->canManage('orders')) {
            $sources[] = ['kind' => 'pending-order', 'category' => 'orders', 'title' => 'Order awaiting confirmation', 'level' => 'info', 'icon' => 'orders', 'entity' => 'id', 'time' => 'created_at', 'message' => 'order_number', 'query' => DB::table('orders')->where('order_status', 'pending')];
        }
        if (auth()->user()->canManage('payments')) {
            $sources[] = ['kind' => 'failed-payment', 'category' => 'payments', 'title' => 'Payment failed', 'level' => 'error', 'icon' => 'wallet', 'entity' => 'id', 'time' => 'COALESCE(updated_at, created_at)', 'message' => "'Order #' || order_id || ' · ' || payment_method", 'query' => DB::table('payments')->where('status', 'failed')];
            $sources[] = ['kind' => 'refund-request', 'category' => 'payments', 'title' => 'Refund request awaiting review', 'level' => 'warning', 'icon' => 'wallet', 'entity' => 'id', 'time' => 'created_at', 'message' => "'Order #' || order_id", 'query' => DB::table('refund_requests')->where('status', 'requested')];
        }
        if (auth()->user()->canManage('reviews')) {
            $sources[] = ['kind' => 'pending-review', 'category' => 'reviews', 'title' => 'Review awaiting moderation', 'level' => 'info', 'icon' => 'spark', 'entity' => 'id', 'time' => 'created_at', 'message' => "'Product #' || product_id", 'query' => DB::table('reviews')->where('status', 'pending')];
        }

        return $sources;
    }

    public function summary(): array
    {
        $counts = array_fill_keys(array_keys($this->categories()), 0);
        foreach ($this->sources() as $source) {
            $counts[$source['category']] += (clone $source['query'])->count();
        }
        $counts['all'] = array_sum(array_diff_key($counts, ['all' => true, 'activity' => true]));

        return $counts;
    }

    public function query(string $category = 'all'): Builder
    {
        abort_unless(array_key_exists($category, $this->categories()), 403);
        if ($category === 'activity') {
            return DB::table('activity_logs')->selectRaw("id as entity_id, 'activity' as kind, 'activity' as category, 'Administrator change' as title, 'info' as level, 'shield' as icon, action as message, created_at as occurred_at")->orderByDesc('id');
        }
        $union = null;
        foreach ($this->sources() as $source) {
            if ($category !== 'all' && $source['category'] !== $category) {
                continue;
            }
            $part = (clone $source['query'])->selectRaw($source['entity'].' as entity_id, ? as kind, ? as category, ? as title, ? as level, ? as icon, '.$source['message'].' as message, '.$source['time'].' as occurred_at', [$source['kind'], $source['category'], $source['title'], $source['level'], $source['icon']]);
            if ($union === null) {
                $union = $part;
            } else {
                $union->unionAll($part);
            }
        }
        if ($union === null) {
            $union = DB::table('users')->whereRaw('1 = 0')->selectRaw("id as entity_id, '' as kind, '' as category, '' as title, '' as level, '' as icon, '' as message, created_at as occurred_at");
        }

        return DB::query()->fromSub($union, 'system_alerts')->orderByRaw('CASE WHEN occurred_at IS NULL THEN 1 ELSE 0 END')->orderByDesc('occurred_at')->orderBy('kind')->orderByDesc('entity_id');
    }

    public function decorate(Collection $items): Collection
    {
        return $items->map(function (object $item): object {
            $item->url = match ($item->kind) {
                'draft-product' => route('products.edit', $item->entity_id),
                'low-stock' => route('inventory.history', $item->entity_id),
                'expired-reservation' => route('inventory.reservations', ['status' => 'pending']),
                'pending-order' => route('orders.show', $item->entity_id),
                'failed-payment' => route('payments'), 'refund-request' => route('refunds'),
                'pending-review' => route('management.edit', ['reviews', $item->entity_id]),
                default => route('audit'),
            };

            return $item;
        });
    }
}
