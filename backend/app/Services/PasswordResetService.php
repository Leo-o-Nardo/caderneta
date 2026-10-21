<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PasswordResetService
{
    private const EXPIRES_MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    public function sendCode(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );
        RateLimiter::clear($this->attemptsKey($email));

        $user->notify(new PasswordResetCode($code, self::EXPIRES_MINUTES));
    }

    public function reset(string $email, string $code, string $password): bool
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        $key = $this->attemptsKey($email);

        if (! $row || Carbon::parse($row->created_at)->addMinutes(self::EXPIRES_MINUTES)->isPast()) {
            return false;
        }

        if (! Hash::check($code, $row->token)) {
            RateLimiter::hit($key, self::EXPIRES_MINUTES * 60);

            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }

            return false;
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['password' => $password])->save();
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        RateLimiter::clear($key);

        return true;
    }

    private function attemptsKey(string $email): string
    {
        return 'password-reset:'.$email;
    }
}
