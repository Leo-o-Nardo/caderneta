<?php

namespace App\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    /** Avisos do próprio sistema (conta vencendo, orçamento estourado), na fase 7. */
    case Event = 'event';
}
