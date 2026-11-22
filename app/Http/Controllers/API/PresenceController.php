<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PresenceController extends Controller
{
    /** Ids of the users who are online right now. Administrators only. */
    public function index(Request $request)
    {
        abort_unless(in_array(strtolower((string) $request->user()->role), ['admin', 'manager'], true), 403);

        $ids = User::pluck('id');
        $keys = $ids->map(fn ($id) => 'user-online:' . $id)->all();
        try {
            $found = Cache::many($keys);
        } catch (\Throwable $e) {
            $found = [];
        }

        return response()->json(
            $ids->filter(fn ($id) => ! empty($found['user-online:' . $id]))
                ->map(fn ($id) => (string) $id)
                ->values()
        );
    }
}