<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalizesEmail;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // Uma senha só: o app mostra o que foi digitado (o olho) e a regra antes de enviar.
            'password' => ['required', Password::defaults()],
            'device_name' => ['required', 'string', 'max:100'],
            'invite_code' => ['nullable', 'string', 'size:6'],
        ];
    }
}
