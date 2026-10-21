<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Uma mensagem de voz: a gravação (até 60 s), a duração e de onde veio (Chat ou Início). */
class SendVoiceRequest extends FormRequest
{
    // O .m4a do app: o tipo é conferido pelo conteúdo do arquivo, não pelo nome. Conforme o celular,
    // o mesmo arquivo é reconhecido como audio/mp4, audio/x-m4a ou até video/mp4.
    public const MIME_TYPES = ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4', 'audio/aac', 'audio/x-hx-aac-adts'];

    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'max:'.config('assistant.voice.max_kb'), 'mimetypes:'.implode(',', self::MIME_TYPES)],
            // Menos de meio segundo é toque sem querer; mais de 60 s, gravação esquecida ligada.
            'duration_ms' => ['required', 'integer', 'min:500', 'max:'.config('assistant.voice.max_seconds') * 1000],
            'source' => ['nullable', Rule::in(['chat', 'home'])],
        ];
    }
}
