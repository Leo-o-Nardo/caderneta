<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Excluir a conta pelo app pede a senha: um celular destravado na mão de outra pessoa não basta. */
class DeleteAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.current_password' => __('auth.password'),
        ];
    }
}
