<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.account.addresses', ['addresses' => DB::table('addresses')->where('user_id', $request->user('web')->id)->orderByDesc('is_default')->orderByDesc('id')->get()]);
    }

    public function form(Request $request, ?int $address = null): View
    {
        $item = $address ? DB::table('addresses')->where('user_id', $request->user('web')->id)->find($address) : null;
        abort_if($address && ! $item, 404);

        return view('pages.account.address-form', ['address' => $item]);
    }

    public function save(Request $request, ?int $address = null): RedirectResponse
    {
        $data = $request->validate(['full_name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:50'], 'province' => ['required', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'], 'district' => ['nullable', 'string', 'max:100'], 'street_address' => ['required', 'string', 'max:2000'], 'postal_code' => ['nullable', 'string', 'max:30'], 'is_default' => ['sometimes', 'boolean']]);
        $userId = $request->user('web')->id;
        DB::transaction(function () use ($userId, $address, $data): void {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $query = DB::table('addresses')->where('user_id', $userId);
            $existing = $address ? (clone $query)->find($address) : null;
            abort_if($address && ! $existing, 404);
            $default = ($data['is_default'] ?? false) || (bool) $existing?->is_default || ! (clone $query)->exists();
            if ($default) {
                (clone $query)->update(['is_default' => false, 'updated_at' => now()]);
            }
            $values = $data + ['user_id' => $userId];
            $values['is_default'] = $default;
            $values['updated_at'] = now();
            if ($address) {
                (clone $query)->where('id', $address)->update($values);
            } else {
                DB::table('addresses')->insert($values + ['created_at' => now()]);
            }
        }, 5);

        return redirect()->route('account.addresses')->with('account_status', 'Address saved.');
    }

    public function remove(Request $request, int $address): RedirectResponse
    {
        $userId = $request->user('web')->id;
        DB::transaction(function () use ($userId, $address): void {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $query = DB::table('addresses')->where('user_id', $userId);
            $existing = (clone $query)->find($address);
            abort_unless($existing, 404);
            abort_if(DB::table('orders')->where('address_id', $address)->exists(), 422, 'This address is linked to an order. It cannot be deleted.');
            (clone $query)->where('id', $address)->delete();
            if ($existing->is_default) {
                $next = (clone $query)->orderBy('id')->value('id');
                if ($next) {
                    (clone $query)->where('id', $next)->update(['is_default' => true, 'updated_at' => now()]);
                }
            }
        }, 5);

        return back()->with('account_status', 'Address removed.');
    }
}
