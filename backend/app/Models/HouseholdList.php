<?php

namespace App\Models;

use App\Enums\ListKind;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma lista da casa (mercado, desejos, coisas a fazer...). Chama HouseholdList porque "List" é
 * palavra reservada do PHP. is_default e position são controlados pelo ListService.
 */
#[Fillable(['name', 'kind', 'icon', 'visibility'])]
class HouseholdList extends Model
{
    use BelongsToHousehold, HasVisibility;

    protected $table = 'lists';

    protected $attributes = [
        'visibility' => 'household',
        'is_default' => false,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => ListKind::class,
            'is_default' => 'boolean',
            'position' => 'integer',
            'visibility' => Visibility::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
