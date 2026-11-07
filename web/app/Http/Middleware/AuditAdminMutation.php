<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminMutation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next): Response {
            $response = $next($request);
            if (! $request->isMethod('GET') && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                DB::table('activity_logs')->insert([
                    'user_id' => $request->user()->id, 'action' => strtolower($request->method()).' '.$request->path(),
                    'entity_type' => $request->segment(4), 'entity_id' => null,
                    'metadata' => json_encode(['status' => $response->getStatusCode()]), 'created_at' => now(),
                ]);
            }

            return $response;
        }, 5);
    }
}
