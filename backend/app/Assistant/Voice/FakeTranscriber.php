<?php

namespace App\Assistant\Voice;

/**
 * O transcritor dos testes automáticos: devolve sempre a mesma frase (config
 * assistant.fake.transcript), sem chamar ninguém. O app de verdade usa o Gemini.
 */
final class FakeTranscriber implements Transcriber
{
    public function __construct(private readonly string $text) {}

    public function transcribe(string $path, string $mimeType, array $hints = []): Transcript
    {
        return new Transcript($this->text);
    }
}
