<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O limite mensal de gasto de uma categoria de despesa. Vale para todos os meses. */
#[Fillable(['category_id', 'limit_cents'])]
class Budget extends Model
{
    use BelongsToHousehold;

    protected function casts(): array
    {
        return [
            'limit_cents' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
