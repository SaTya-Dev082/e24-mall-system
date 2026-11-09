<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomerAnnouncements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerAnnouncementController extends Controller
{
    public function store(Request $request, CustomerAnnouncements $announcements): JsonResponse
    {
        abort_unless($request->user()->canManage('customers'), 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'], 'action' => ['required', Rule::in(array_keys(CustomerAnnouncements::ACTIONS))],
            'audience' => ['required', 'in:customer,all'],
            'recipient_id' => ['exclude_unless:audience,customer', 'required', 'integer', Rule::exists('users', 'id')->where('role', 'customer')->where('status', 'active')->whereNull('deleted_at')],
        ]);
        $campaign = $announcements->create($request->user(), $data);
        if ($campaign->status === 'pending') {
            $announcements->enqueue($campaign->id);
        }

        return response()->json(['id' => $campaign->id, 'status' => $campaign->status], 202);
    }

    public function retry(Request $request, string $campaign, CustomerAnnouncements $announcements): JsonResponse
    {
        abort_unless($request->user()->canManage('customers'), 403);
        DB::transaction(function () use ($campaign): void {
            $row = DB::table('customer_notification_campaigns')->where('id', $campaign)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->status === 'failed') {
                DB::table('customer_notification_campaigns')->where('id', $campaign)->update(['status' => 'pending', 'updated_at' => now()]);
            }
        });
        $announcements->enqueue($campaign);

        return response()->json(['message' => 'Delivery retry requested.'], 202);
    }
}
