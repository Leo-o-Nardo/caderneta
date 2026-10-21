<?php

use App\Enums\HouseholdRole;
use App\Models\Card;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdList;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Classe base dos testes
|--------------------------------------------------------------------------
|
| Os testes de Feature rodam sobre o TestCase do Laravel (com a aplicação carregada) e com o
| banco de testes zerado a cada teste. Os testes de Unit rodam sem a aplicação.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| Verificações próprias que podem ser usadas com expect(). Esta veio de exemplo com o Pest.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
|
| Funções globais usadas em vários arquivos de teste.
|
*/

/**
 * Cria uma casa com $owner como dono e adiciona os membros passados como [[User, 'member'], ...].
 * Como no app, a casa vira a atual de todos que entram nela.
 */
function householdOf(User $owner, array $members = []): Household
{
    $service = app(HouseholdService::class);
    $household = $service->createFor($owner, 'Casa de '.$owner->name);

    foreach ($members as [$user, $role]) {
        $service->addMember($household, $user, HouseholdRole::from($role));
    }

    return $household;
}

/**
 * Uma das categorias padrão da casa. Ignora os filtros automáticos, para funcionar mesmo
 * depois de uma requisição ter deixado o contexto preenchido com outra casa.
 */
function categoryOf(Household $household, string $name, string $kind = 'expense'): Category
{
    return Category::withoutGlobalScopes()
        ->where('household_id', $household->id)
        ->where('kind', $kind)
        ->where('name', $name)
        ->firstOrFail();
}

/** Um cartão de $owner na casa. Por padrão fecha dia 3 e vence dia 10. */
function cardOf(Household $household, User $owner, array $attributes = []): Card
{
    return Card::factory()->for($household)->for($owner)->create($attributes);
}

/** Uma lista da casa pelo nome, ignorando os filtros automáticos (como o categoryOf). */
function listOf(Household $household, string $name): HouseholdList
{
    return HouseholdList::withoutGlobalScopes()
        ->where('household_id', $household->id)
        ->where('name', $name)
        ->firstOrFail();
}

/** O corpo SSE de uma resposta simulada do Gemini (streamGenerateContent): uma linha "data:" por pedaço. */
function geminiSse(array ...$chunks): string
{
    return implode('', array_map(fn (array $chunk) => 'data: '.json_encode($chunk)."\r\n\r\n", $chunks));
}

/** Um pedaço da resposta do Gemini, com as partes do modelo e, no último, o motivo de parada e os tokens. */
function geminiChunk(array $parts, ?string $finish = null, ?array $usage = null): array
{
    return array_filter([
        'candidates' => [array_filter(['content' => ['role' => 'model', 'parts' => $parts], 'finishReason' => $finish])],
        'usageMetadata' => $usage,
        'modelVersion' => 'gemini-3.5-flash-lite',
    ]);
}

/** Lança uma despesa (ou receita) pela API, como o usuário que estiver logado no teste. */
function spend(Household $house, string $category, int $cents, string $date = '2026-10-10', array $extra = []): void
{
    $kind = $extra['kind'] ?? 'expense';

    test()->postJson('/api/v1/transactions', array_merge([
        'kind' => $kind,
        'amount_cents' => $cents,
        'description' => $category,
        'occurred_on' => $date,
        'category_id' => categoryOf($house, $category, $kind)->id,
        'payment_type' => $kind === 'expense' ? 'pix' : null,
    ], $extra))->assertCreated();
}
