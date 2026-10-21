<?php

namespace App\Enums;

enum PaymentType: string
{
    case Debit = 'debit';
    case CreditCard = 'credit_card';
    case Pix = 'pix';
    case Cash = 'cash';
}
