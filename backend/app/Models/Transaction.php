<?php

namespace App\Models;

use App\Enums\CategoryKind;
use App\Enums\PaymentType;
use App\Enums\TransactionSource;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasVisibility;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um gasto ou uma receita. O valor é sempre positivo, em centavos; o "kind" diz o sinal.
 * created_by, source, invoice_month, os campos de parcela e o recurring_income_id são
 * preenchidos pelos serviços.
 */
#[Fillable([
    'kind', 'amount_cents', 'description', 'occurred_on', 'category_id',
    'payment_type', 'card_id', 'visibility',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToHousehold, HasFactory, HasVisibility;

    protected $attributes = [
        'visibility' => 'household',
        'source' => 'app',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
            'amount_cents' => 'integer',
            'occurred_on' => 'immutable_date',
            'payment_type' => PaymentType::class,
            'invoice_month' => 'immutable_date',
            'installment_number' => 'integer',
            'visibility' => Visibility::class,
            'source' => TransactionSource::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class);
    }

    public function recurringIncome(): BelongsTo
    {
        return $this->belongsTo(RecurringIncome::class);
    }
}
