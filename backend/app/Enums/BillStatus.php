<?php

namespace App\Enums;

/** A situação de uma conta fixa num mês. Só "paid" e "skipped" ficam gravados no banco. */
enum BillStatus: string
{
    case Paid = 'paid';
    case Skipped = 'skipped';
    case Overdue = 'overdue';
    case DueToday = 'due_today';
    case Upcoming = 'upcoming';
}
