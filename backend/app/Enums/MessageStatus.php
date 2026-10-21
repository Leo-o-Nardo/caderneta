<?php

namespace App\Enums;

enum MessageStatus: string
{
    /** A resposta ainda está sendo preparada (o app mostra "pensando…"). */
    case Pending = 'pending';
    /** A mensagem de voz ainda está virando texto (o app mostra "transcrevendo…"). */
    case Transcribing = 'transcribing';
    case Completed = 'completed';
    case Failed = 'failed';
}
