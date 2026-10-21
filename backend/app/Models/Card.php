<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Support\InvoiceCalendar;
use App\Support\InvoicePeriod;
use Carbon\CarbonInterface;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Cartão de crédito. Débito, Pix e dinheiro são só um tipo de pagamento no lançamento. */
#[Fillable(['name', 'closing_day', 'due_day', 'limit_cents', 'color'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use BelongsToHousehold, HasFactory;

    // O banco já tem esse padrão, mas um objeto recém-criado não sabe disso e viria com null.
    protected $attributes = [
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'closing_day' => 'integer',
            'due_day' => 'integer',
            'limit_cents' => 'integer',
            'is_default' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function invoiceFor(CarbonInterface $purchasedOn): InvoicePeriod
    {
        return InvoiceCalendar::for($this->closing_day, $this->due_day, $purchasedOn);
    }

    /** Torna este o cartão usado quando o dono diz só "no cartão". */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::query()->where('user_id', $this->user_id)->whereKeyNot($this->id)->update(['is_default' => false]);
            $this->forceFill(['is_default' => true])->save();
        });
    }

    /** Tira o cartão dos novos lançamentos; se ele era o padrão, o cartão mais antigo do dono assume. */
    public function archive(): void
    {
        DB::transaction(function () {
            $wasDefault = $this->is_default;
            $this->forceFill(['archived_at' => now(), 'is_default' => false])->save();

            if ($wasDefault) {
                static::query()->active()->where('user_id', $this->user_id)->oldest('id')->first()?->makeDefault();
            }
        });
    }
}
