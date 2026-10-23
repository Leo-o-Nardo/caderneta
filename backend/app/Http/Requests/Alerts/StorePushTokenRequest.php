<?php

namespace App\Http\Requests\Alerts;

use Illuminate\Foundation\Http\FormRequest;

/** O celular se registrando para receber os avisos: o endereço dele na Expo e o sistema. */
class StorePushTokenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[^\]]+\]$/'],
            'platform' => ['required', 'in:android,ios'],
        ];
    }
}
