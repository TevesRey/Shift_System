<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/** Password reset with a 6-digit code that is emailed to the user. */
class PasswordCodeController extends Controller
{
    private const LIFETIME_SECONDS = 600;
    private const MAX_ATTEMPTS = 5;

    /** Step 1: email a verification code. The reply is the same whether or not the account exists. */
    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower(trim($data['email']));

        $cooldown = 'pwreset-cooldown:' . $email;
        if (Cache::has($cooldown)) {
            return response()->json(['message' => 'Please wait a moment before requesting another code.'], 429);
        }
        Cache::put($cooldown, 1, now()->addSeconds(45));

        $generic = ['message' => 'If an account exists for that email, a 6-digit verification code has been sent. It expires in 10 minutes.'];

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return response()->json($generic);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put('pwreset:' . $email, [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => time() + self::LIFETIME_SECONDS,
        ], now()->addSeconds(self::LIFETIME_SECONDS));

        try {
            $body = 'Hello ' . $user->full_name . ",\n\n"
                . 'Your ShiftMate password reset code is: ' . $code . "\n\n"
                . "This code expires in 10 minutes. If you did not request it, you can ignore this email.\n";
            Mail::raw($body, function ($message) use ($user) {
                $message->to($user->email)->subject('Your ShiftMate password reset code');
            });
        } catch (\Throwable $exception) {
            Log::error('ShiftMate password reset code email failed.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
            Cache::forget('pwreset:' . $email);
            Cache::forget($cooldown);

            return response()->json(['message' => 'The verification code could not be sent. Please try again later.'], 503);
        }

        return response()->json($generic);
    }

    /** Step 2: check the code. On success hand back a one-time token for the reset-password call. */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);
        $email = strtolower(trim($data['email']));
        $key = 'pwreset:' . $email;
        $invalid = response()->json(['message' => 'That code is incorrect or has expired.'], 422);

        $entry = Cache::get($key);
        if (! is_array($entry) || ($entry['expires_at'] ?? 0) < time()) {
            Cache::forget($key);

            return $invalid;
        }

        if (($entry['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return response()->json(['message' => 'Too many incorrect attempts. Please request a new code.'], 429);
        }

        if (! Hash::check($data['code'], (string) $entry['hash'])) {
            $entry['attempts'] = ($entry['attempts'] ?? 0) + 1;
            Cache::put($key, $entry, now()->addSeconds(max(1, $entry['expires_at'] - time())));

            return $invalid;
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return $invalid;
        }

        Cache::forget($key);

        return response()->json([
            'message' => 'Code verified. Choose a new password.',
            'email' => $email,
            'reset_token' => Password::broker()->createToken($user),
        ]);
    }
}