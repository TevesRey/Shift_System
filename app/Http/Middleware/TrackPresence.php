<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Marks the signed-in user as online every time their app talks to the server. */
class TrackPresence
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            try {
                $key = 'user-online:' . $user->id;
                if (time() - (int) Cache::get($key, 0) > 30) {
                    Cache::put($key, time(), now()->addSeconds(150));
                }
            } catch (\Throwable $e) {
                // Never block a request just because presence tracking failed.
            }
        }

        return $next($request);
    }
}