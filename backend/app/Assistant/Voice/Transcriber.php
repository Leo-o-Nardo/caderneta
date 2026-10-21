<?php

namespace App\Assistant\Voice;

use App\Assistant\Llm\LlmException;

/**
 * Quem transforma a gravação em texto. Hoje o Gemini; trocar por outro serviço (Groq, Whisper) é
 * criar outra classe e mudar ASSISTANT_TRANSCRIBER no .env.
 */
interface Transcriber
{
    /**
     * Texto vazio quando não há fala no áudio.
     *
     * @param  string  $path  o arquivo de áudio no disco
     * @param  list<string>  $hints  nomes da casa que podem aparecer na fala ("Nubank", "Mercado")
     *
     * @throws LlmException
     */
    public function transcribe(string $path, string $mimeType, array $hints = []): Transcript;
}
