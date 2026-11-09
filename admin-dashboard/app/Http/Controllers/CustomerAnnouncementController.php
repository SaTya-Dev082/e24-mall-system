<?php

namespace App\Http\Controllers;

use App\Services\ShopApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerAnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManage('customers'), 403);
        $campaigns = DB::table('customer_notification_campaigns')->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        $customers = DB::table('users')->where('role', 'customer')->where('status', 'active')->whereNull('deleted_at')->orderByDesc('id')->limit(30)->get(['id', 'name', 'email']);
        $selectedId = old('recipient_id');
        if (is_scalar($selectedId) && $selectedId && ! $customers->contains('id', (int) $selectedId)) {
            $selected = DB::table('users')->where('id', $selectedId)->where('role', 'customer')->whereNull('deleted_at')->first(['id', 'name', 'email']);
            if ($selected) {
                $customers->prepend($selected);
            }
        }

        return view('management.customer-announcements', compact('campaigns', 'customers') + ['requestKey' => (string) Str::uuid()]);
    }

    public function recipients(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManage('customers'), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = strtolower($data['search'] ?? '');
        $customers = DB::table('users')->where('role', 'customer')->where('status', 'active')->whereNull('deleted_at')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])->orWhereRaw('LOWER(email) LIKE ?', ['%'.$search.'%'])))
            ->orderByDesc('id')->limit(30)->get(['id', 'name', 'email']);

        return response()->json(['customers' => $customers])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, ShopApi $api): RedirectResponse
    {
        abort_unless($request->user()->canManage('customers'), 403);
        $api->send('POST', 'admin/customer-notifications', $request->only(['request_key', 'audience', 'recipient_id', 'title', 'message', 'action']));

        return redirect()->route('customer-announcements.index')->with('status', 'Announcement accepted. Delivery runs in the customer notifications queue.');
    }

    public function retry(Request $request, string $campaign, ShopApi $api): RedirectResponse
    {
        abort_unless($request->user()->canManage('customers'), 403);
        $api->send('POST', 'admin/customer-notifications/'.$campaign.'/retry');

        return redirect()->route('customer-announcements.index')->with('status', 'Delivery retry requested.');
    }
}
