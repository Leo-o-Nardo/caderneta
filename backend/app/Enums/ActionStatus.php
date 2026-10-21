<?php

namespace App\Enums;

/** A vida de uma ação do assistente: pendente até a pessoa decidir, ou feita e desfeita. */
enum ActionStatus: string
{
    case Pending = 'pending';
    case Executed = 'executed';
    case Cancelled = 'cancelled';
    /** Ninguém confirmou em 30 minutos. */
    case Expired = 'expired';
    /** Feita na hora e desfeita pelo card (só itens de lista e lembretes). */
    case Undone = 'undone';
}
