<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class CustomerNotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.notifications', [
            'notifications' => $request->user('web')->notifications()->orderByDesc('id')->paginate(20),
            'unreadCount' => $request->user('web')->unreadNotifications()->count(),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $request->validate(['summary' => ['sometimes', 'in:1']]);
        $user = $request->user('web');
        $data = ['unread_count' => $user->unreadNotifications()->count()];
        if (! $request->boolean('summary')) {
            $data['items'] = $user->notifications()->orderByDesc('id')->limit(8)->get()->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id, 'title' => $notification->data['title'] ?? 'Store update',
                'message' => $notification->data['message'] ?? '', 'unread' => $notification->read_at === null,
                'created_at' => $notification->created_at->toIso8601String(), 'age' => $notification->created_at->diffForHumans(),
                'read_url' => route('storefront.notifications.read', $notification->id),
            ]);
        }

        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    public function read(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $item = $request->user('web')->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();
        $destination = match ($item->data['action'] ?? 'products') {
            'home' => 'home', 'cart' => 'storefront.cart.index', 'profile' => 'account.profile',
            default => 'storefront.products.index',
        };
        $url = route($destination);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $request->user('web')->unreadNotifications()->update(['read_at' => now(), 'updated_at' => now()]);

        return $request->expectsJson() ? response()->json(['unread_count' => 0]) : back();
    }
}
