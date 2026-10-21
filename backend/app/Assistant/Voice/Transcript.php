<?php

namespace App\Assistant\Voice;

use App\Assistant\Llm\TokenUsage;

/** O texto da gravação e o que custou (null no transcritor simulado). */
final readonly class Transcript
{
    public function __construct(
        public string $text,
        public ?TokenUsage $usage = null,
        public ?string $model = null,
    ) {}
}
