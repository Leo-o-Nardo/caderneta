<?php

namespace App\Enums;

/** De onde veio o lançamento. */
enum TransactionSource: string
{
    case App = 'app';
    case Assistant = 'assistant';
    // Lançado sozinho por uma rotina: receita fixa (salário) ou conta de débito automático.
    case Recurring = 'recurring';
}
