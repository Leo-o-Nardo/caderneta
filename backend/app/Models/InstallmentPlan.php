<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Uma compra parcelada no cartão. Cada parcela é um Transaction ligado a este plano. */
#[Fillable([
    'description', 'category_id', 'card_id', 'total_cents', 'installments',
    'purchased_on', 'visibility',
])]
class InstallmentPlan extends Model
{
    use BelongsToHousehold, HasVisibility;

    protected $attributes = [
        'visibility' => 'household',
        'source' => 'app',
    ];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'installments' => 'integer',
            'purchased_on' => 'immutable_date',
            'visibility' => Visibility::class,
            'source' => TransactionSource::class,
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
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
}
