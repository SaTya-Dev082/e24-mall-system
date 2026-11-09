<?php

namespace App\Services;

use App\Jobs\DeliverCustomerAnnouncement;
use App\Models\User;
use App\Notifications\CustomerAnnouncement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerAnnouncements
{
    public const ACTIONS = ['home' => 'Home', 'products' => 'All products', 'cart' => 'Shopping cart', 'profile' => 'My profile'];

    public function create(User $actor, array $data): object
    {
        return DB::transaction(function () use ($actor, $data): object {
            $freshActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($freshActor->canManage('customers'), 403);
            $existing = DB::table('customer_notification_campaigns')->where('created_by', $actor->id)->where('request_key', $data['request_key'])->first();
            $recipientId = $data['audience'] === 'customer' ? (int) $data['recipient_id'] : null;
            if ($existing) {
                foreach (['title', 'message', 'action', 'audience'] as $field) {
                    if ($data[$field] !== $existing->$field) {
                        throw ValidationException::withMessages(['request_key' => 'This send request was already used. Reload the page for a new announcement.']);
                    }
                }
                if ($existing->recipient_id !== $recipientId) {
                    throw ValidationException::withMessages(['request_key' => 'This send request was already used for another recipient.']);
                }

                return $existing;
            }
            $id = (string) Str::uuid();
            DB::table('customer_notification_campaigns')->insert([
                'id' => $id, 'created_by' => $actor->id, 'request_key' => $data['request_key'],
                'audience' => $data['audience'], 'recipient_id' => $recipientId,
                'title' => $data['title'], 'message' => $data['message'], 'action' => $data['action'],
                'upper_user_id' => DB::table('users')->max('id') ?? 0, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('customer_notification_campaigns')->where('id', $id)->first();
        });
    }

    public function enqueue(string $campaignId): void
    {
        DB::afterCommit(function () use ($campaignId): void {
            try {
                DeliverCustomerAnnouncement::dispatch($campaignId);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function deliverBatch(string $campaignId): bool
    {
        return DB::transaction(function () use ($campaignId): bool {
            $campaign = DB::table('customer_notification_campaigns')->where('id', $campaignId)->lockForUpdate()->first();
            if (! $campaign || $campaign->status !== 'pending') {
                return false;
            }
            $users = DB::table('users')->where('role', 'customer')->where('status', 'active')->whereNull('deleted_at')
                ->where('id', '>', $campaign->cursor_user_id)->where('id', '<=', $campaign->upper_user_id)
                ->when($campaign->audience === 'customer', fn ($query) => $query->where('id', $campaign->recipient_id ?? 0))
                ->orderBy('id')->limit(200)->pluck('id');
            $now = now();
            $data = json_encode((new CustomerAnnouncement($campaign->title, $campaign->message, $campaign->action))->payload(), JSON_THROW_ON_ERROR);
            $rows = $users->map(fn ($id) => [
                'id' => (string) Str::uuid(), 'type' => CustomerAnnouncement::class,
                'notifiable_type' => (new User)->getMorphClass(), 'notifiable_id' => $id,
                'campaign_id' => $campaignId, 'data' => $data, 'read_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ])->all();
            $count = $rows ? DB::table('notifications')->insertOrIgnore($rows) : 0;
            $more = $users->count() === 200;
            DB::table('customer_notification_campaigns')->where('id', $campaignId)->update([
                'cursor_user_id' => $users->last() ?? $campaign->cursor_user_id,
                'delivered_count' => $campaign->delivered_count + $count, 'status' => $more ? 'pending' : 'completed',
                'completed_at' => $more ? null : $now, 'updated_at' => $now,
            ]);

            return $more;
        });
    }
}
