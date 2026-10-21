<?php

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('resets the password with the e-mailed code', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'leo@example.com']);
    $user->createToken('old-phone');

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'leo@example.com'])->assertNoContent();

    $code = null;
    Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'leo@example.com',
        'code' => $code,
        'password' => 'newpass123',
    ])->assertNoContent();

    expect(Hash::check('newpass123', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

it('does not reveal whether the e-mail exists', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertNoContent();

    Notification::assertNothingSent();
});

it('discards the code after too many wrong attempts', function () {
    Notification::fake();
    User::factory()->create(['email' => 'leo@example.com']);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'leo@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'leo@example.com',
            'code' => '000000',
            'password' => 'newpass123',
        ])->assertJsonValidationErrors('code');
    }

    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('changes the password and signs out the other devices', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phone')->plainTextToken;
    $user->createToken('tablet');

    $this->withToken($token)->putJson('/api/v1/me/password', [
        'current_password' => 'password',
        'password' => 'newpass123',
    ])->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['phone']);
});
