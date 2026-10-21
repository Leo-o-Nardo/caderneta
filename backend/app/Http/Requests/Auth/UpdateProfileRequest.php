<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * O e-mail não muda por aqui: trocar o e-mail de login pede confirmação, que fica para depois.
 * Os avisos (Configurações › Avisos) chegam só com os tipos que mudaram: {"weekly": false}.
 */
class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            // Só os tipos que dá para desligar: o lembrete sempre avisa.
            'alert_settings' => ['sometimes', 'required', 'array:'.implode(',', User::ALERT_KINDS)],
            'alert_settings.*' => ['boolean'],
        ];
    }
}
