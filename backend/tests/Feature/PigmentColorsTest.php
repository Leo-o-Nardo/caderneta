<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// A etapa 4 do redesign: as cores neon viram os tons de pigmento do guia (docs/design-guide.html, parte 02).

/** A migration da troca de cores, para rodar de novo sobre dados com as cores antigas. */
function pigmentMigration(): object
{
    return require database_path('migrations/2026_10_20_100000_convert_colors_to_pigment.php');
}

it('gives new households the pigment colors', function () {
    $house = householdOf(User::factory()->create());

    expect(categoryOf($house, 'Mercado')->color)->toBe('#4F7F4F')
        ->and(categoryOf($house, 'Alimentação')->color)->toBe('#B8902F')
        ->and(categoryOf($house, 'Outros')->color)->toBe('#7D786D')
        ->and(categoryOf($house, 'Salário', 'income')->color)->toBe('#4F7F4F');
});

it('converts the neon colors to pigment and back on rollback', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $mercado = categoryOf($house, 'Mercado')->id;
    $outros = categoryOf($house, 'Outros')->id;
    $card = cardOf($house, $leo)->id;

    // Como estava antes da etapa 4: cor neon (às vezes em minúsculas) e uma cor própria, fora da tabela.
    DB::table('categories')->where('id', $mercado)->update(['color' => '#3dd68c']);
    DB::table('categories')->where('id', $outros)->update(['color' => '#123456']);
    DB::table('cards')->where('id', $card)->update(['color' => '#A78BFA']);

    // Uma ficha do chat e uma ação do assistente que guardaram a cor da categoria Delivery.
    $conversation = DB::table('conversations')->insertGetId([
        'user_id' => $leo->id, 'household_id' => $house->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $snapshot = ['cards' => [['type' => 'transactions', 'items' => [['category' => ['name' => 'Delivery', 'color' => '#F87171']]]]]];
    $message = DB::table('messages')->insertGetId([
        'conversation_id' => $conversation, 'role' => 'assistant', 'status' => 'completed',
        'metadata' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $action = DB::table('pending_actions')->insertGetId([
        'ulid' => (string) Str::ulid(), 'household_id' => $house->id, 'user_id' => $leo->id, 'message_id' => $message,
        'tool' => 'create_transactions', 'tier' => 'confirm', 'status' => 'pending',
        'payload' => json_encode(['category' => ['color' => '#f87171']]), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $color = fn (string $table, int $id) => DB::table($table)->where('id', $id)->value('color');
    $messageColor = fn () => json_decode(DB::table('messages')->where('id', $message)->value('metadata'), true)['cards'][0]['items'][0]['category']['color'];
    $actionColor = fn () => json_decode(DB::table('pending_actions')->where('id', $action)->value('payload'), true)['category']['color'];

    $migration = pigmentMigration();
    $migration->up();

    expect($color('categories', $mercado))->toBe('#4F7F4F')
        ->and($color('categories', $outros))->toBe('#123456')
        ->and($color('cards', $card))->toBe('#7E5490')
        ->and($messageColor())->toBe('#B9553A')
        ->and($actionColor())->toBe('#B9553A');

    $migration->down();

    expect($color('categories', $mercado))->toBe('#3DD68C')
        ->and($color('categories', $outros))->toBe('#123456')
        ->and($color('cards', $card))->toBe('#A78BFA')
        ->and($messageColor())->toBe('#F87171');
});
