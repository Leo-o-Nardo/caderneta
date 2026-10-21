<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um mês de uma conta fixa que foi pago (com a despesa lançada) ou pulado. */
#[Fillable(['month', 'status', 'transaction_id'])]
class BillPayment extends Model
{
    use BelongsToHousehold;

    protected function casts(): array
    {
        return [
            'month' => 'immutable_date',
            'status' => BillStatus::class,
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class, 'recurring_bill_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
