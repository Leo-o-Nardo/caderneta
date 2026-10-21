<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request, PasswordResetService $resets): Response
    {
        $resets->sendCode($request->validated('email'));

        return response()->noContent();
    }

    public function reset(ResetPasswordRequest $request, PasswordResetService $resets): Response
    {
        $done = $resets->reset(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('password'),
        );

        if (! $done) {
            throw ValidationException::withMessages(['code' => __('passwords.token')]);
        }

        return response()->noContent();
    }

    public function update(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->noContent();
    }
}
