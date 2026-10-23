<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\HouseholdRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Uma pessoa com login. approved_at e rejected_at são preenchidos pelo AccessService: quem se
 * cadastra sozinho só entra depois de liberado; quem vem por convite já entra liberado.
 */
#[Fillable(['name', 'email', 'password', 'timezone', 'alert_settings'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Os avisos que a pessoa pode desligar em Configurações › Avisos. O lembrete não está aqui:
     * ele existe para avisar, então sempre avisa.
     */
    public const ALERT_KINDS = ['bill_due', 'autopay', 'budget', 'weekly'];

    protected $attributes = [
        'timezone' => 'America/Sao_Paulo',
    ];

    /**
     * Atributos convertidos ao ler e gravar.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'alert_settings' => 'array',
        ];
    }

    /** Se a pessoa quer este tipo de aviso. Tudo começa ligado; ela desliga em Configurações › Avisos. */
    public function wantsAlert(string $kind): bool
    {
        return ($this->alert_settings[$kind] ?? true) !== false;
    }

    /**
     * Cada aviso que dá para desligar, ligado ou não (o que nunca foi mexido conta como ligado).
     *
     * @return array<string, bool>
     */
    public function alertSettings(): array
    {
        return array_combine(self::ALERT_KINDS, array_map($this->wantsAlert(...), self::ALERT_KINDS));
    }

    /** "approved", "pending" (esperando a liberação) ou "rejected". */
    public function accessStatus(): string
    {
        return match (true) {
            $this->approved_at !== null => 'approved',
            $this->rejected_at !== null => 'rejected',
            default => 'pending',
        };
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    public function currentHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'current_household_id');
    }

    /** Os celulares onde a pessoa está logada e aceitou receber notificações. */
    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    public function roleIn(Household $household): ?HouseholdRole
    {
        return $this->households()->whereKey($household->id)->first()?->membership->role;
    }
}
