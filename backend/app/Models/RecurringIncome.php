<?php

namespace App\Models;

use App\Enums\Visibility;
use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasVisibility;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma receita que se repete todo mês no mesmo dia (salário). Cada mês vira um lançamento comum,
 * criado pelo RecurringIncomeService. created_by e launched_through são preenchidos por ele.
 */
#[Fillable(['description', 'category_id', 'amount_cents', 'day_of_month', 'visibility'])]
class RecurringIncome extends Model
{
    use BelongsToHousehold, HasVisibility;

    protected $attributes = [
        'visibility' => 'household',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'day_of_month' => 'integer',
            'visibility' => Visibility::class,
            'launched_through' => 'immutable_date',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** O dia do próximo lançamento: no mês seguinte ao último lançado (dia 31 vira o último dia do mês). */
    public function nextOn(): CarbonImmutable
    {
        return Month::day($this->launched_through->addMonth(), $this->day_of_month);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
