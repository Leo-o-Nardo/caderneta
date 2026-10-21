<?php

namespace App\Support;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Closure;
use LogicException;

/**
 * A casa em que a requisição (ou o job) atual trabalha, com o papel do usuário nela e quem é
 * esse usuário. Preenchido pelo middleware "household" no HTTP e, mais adiante, pelos próprios jobs.
 */
class HouseholdContext
{
    private ?Household $household = null;

    private ?HouseholdRole $role = null;

    private ?User $user = null;

    public function set(Household $household, HouseholdRole $role, ?User $user = null): void
    {
        $this->household = $household;
        $this->role = $role;
        $this->user = $user;
    }

    /**
     * Roda $callback como outra pessoa e depois volta ao contexto de antes. Os avisos usam isso
     * para calcular os números de cada pessoa: com a fila "sync" (nos testes), o job roda no meio
     * de uma requisição, que precisa continuar com o próprio contexto.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Household $household, HouseholdRole $role, ?User $user, Closure $callback): mixed
    {
        $previous = [$this->household, $this->role, $this->user];
        $this->set($household, $role, $user);

        try {
            return $callback();
        } finally {
            [$this->household, $this->role, $this->user] = $previous;
        }
    }

    public function has(): bool
    {
        return $this->household !== null;
    }

    public function household(): Household
    {
        return $this->household ?? throw new LogicException('No household in context.');
    }

    public function role(): HouseholdRole
    {
        return $this->role ?? throw new LogicException('No household in context.');
    }

    /** Quem está consultando: decide quais lançamentos privados aparecem. */
    public function user(): ?User
    {
        return $this->user;
    }
}
