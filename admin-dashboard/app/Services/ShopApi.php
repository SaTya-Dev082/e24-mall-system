<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ShopApi
{
    /** Credentials stay server-side; the per-request credential expires and is revoked immediately. */
    public function send(string $method, string $path, array $payload = [], ?UploadedFile $image = null): array
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'admin' && $user->status === 'active', 403);
        $token = $user->createToken('admin-dashboard-request', ['admin'], now()->addMinute());
        try {
            $client = Http::baseUrl(rtrim(config('services.shop.url'), '/'))
                ->acceptJson()->withToken($token->plainTextToken)->connectTimeout(3)->timeout(12)
                ->withOptions(['allow_redirects' => false]);
            $response = $image
                ? $client->attach('image', file_get_contents($image->getRealPath()), $image->hashName())->post(ltrim($path, '/'), $payload)
                : $client->send($method, ltrim($path, '/'), ['json' => $payload]);
            if ($response->status() === 422) {
                throw ValidationException::withMessages($response->json('errors') ?: ['operation' => [$response->json('message', 'The request could not be completed.')]]);
            }
            if ($response->status() === 404) {
                abort(404);
            }
            if ($response->status() === 403) {
                abort(403, 'You do not have permission for this operation.');
            }
            if (! $response->successful()) {
                throw ValidationException::withMessages(['operation' => ['The shop service could not complete this action. Please try again.']]);
            }

            return $response->json() ?? [];
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['operation' => ['The shop service is unavailable. Start the web backend on port 8000 and try again.']]);
        } finally {
            $token->accessToken->delete();
        }
    }
}
