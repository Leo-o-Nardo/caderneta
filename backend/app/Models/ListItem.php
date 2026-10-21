<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um item de lista. Todos os campos valem em qualquer lista; o tipo da lista diz quais o app mostra. */
#[Fillable(['text', 'quantity', 'note', 'price_cents', 'url', 'due_on'])]
class ListItem extends Model
{
    use BelongsToHousehold;

    protected $attributes = [
        'position' => 0,
    ];

    protected static function booted(): void
    {
        // O item não tem visibilidade própria: ele aparece só se a lista dele aparece. Assim os
        // itens de uma lista privada de outra pessoa não são encontrados nem pelo id.
        static::addGlobalScope('visible_list', fn (Builder $query) => $query->whereHas('list'));
    }

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'due_on' => 'immutable_date',
            'position' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(HouseholdList::class, 'list_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
