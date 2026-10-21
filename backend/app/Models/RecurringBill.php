<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Uma conta que se repete todo mês (luz, internet, condomínio). Os meses são calculados pelo BillService. */
#[Fillable(['name', 'category_id', 'expected_cents', 'amount_varies', 'due_day', 'autopay'])]
class RecurringBill extends Model
{
    use BelongsToHousehold;

    protected $attributes = [
        'amount_varies' => false,
        'autopay' => false,
    ];

    protected function casts(): array
    {
        return [
            'expected_cents' => 'integer',
            'amount_varies' => 'boolean',
            'due_day' => 'integer',
            'autopay' => 'boolean',
            'starts_on' => 'immutable_date',
            'autopay_through' => 'immutable_date',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
