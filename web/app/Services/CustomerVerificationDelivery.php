<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\CustomerVerifyEmail;
use Illuminate\Support\Facades\DB;
use Throwable;

class CustomerVerificationDelivery
{
    public function request(User $user): void
    {
        DB::table('customer_verification_deliveries')->upsert([
            'user_id' => $user->id, 'attempts' => 0, 'available_at' => now(),
            'queued_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ], ['user_id'], ['attempts', 'available_at', 'queued_at', 'updated_at']);
    }

    public function deliver(int $userId): void
    {
        try {
            DB::transaction(function () use ($userId): void {
                $delivery = DB::table('customer_verification_deliveries')->where('user_id', $userId)
                    ->whereNull('queued_at')->where('available_at', '<=', now())->lockForUpdate()->first();
                if (! $delivery) {
                    return;
                }
                $user = User::find($userId);
                if ($user && ! $user->hasVerifiedEmail() && $user->role === 'customer' && $user->status === 'active' && $user->deleted_at === null) {
                    // The account is committed before this method; enqueue while holding the delivery lock.
                    $user->notify((new CustomerVerifyEmail)->beforeCommit());
                }
                DB::table('customer_verification_deliveries')->where('id', $delivery->id)->update(['queued_at' => now(), 'updated_at' => now()]);
            });
        } catch (Throwable $exception) {
            report($exception);
            // Keep the committed delivery request for a later scheduler attempt.
            try {
                DB::table('customer_verification_deliveries')->where('user_id', $userId)->whereNull('queued_at')
                    ->update(['attempts' => DB::raw('attempts + 1'), 'available_at' => now()->addMinutes(5), 'updated_at' => now()]);
            } catch (Throwable $retryException) {
                report($retryException);
            }
        }
    }
}
