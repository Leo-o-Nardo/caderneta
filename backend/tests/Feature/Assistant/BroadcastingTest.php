<?php

use App\Events\Assistant\AssistantDelta;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;

/**
 * Nos testes o broadcast é "null" (não manda nada). Para testar a autorização dos canais, troca
 * para o driver do Reverb com chaves de mentira (autorizar não conversa com o servidor, só assina)
 * e carrega os canais de novo: eles ficam registrados no driver que era o padrão quando o app subiu.
 */
function useFakeReverb(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'chave-de-teste',
        'broadcasting.connections.reverb.secret' => 'segredo-de-teste',
        'broadcasting.connections.reverb.app_id' => 'teste',
        'broadcasting.connections.reverb.options.host' => 'localhost',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);

    require base_path('routes/channels.php');
}

it('lets each person listen only to their own channel', function () {
    useFakeReverb();
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-user.{$leo->id}"])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-user.{$ana->id}"])
        ->assertForbidden();
});

it('keeps answering when sending to the live channel fails', function () {
    // Simula o Reverb fora do ar: mandar um pedaço da resposta dá erro.
    Exceptions::fake();
    Event::listen(AssistantDelta::class, fn () => throw new RuntimeException('Reverb fora do ar'));

    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/conversation/messages', ['text' => 'bom dia'])
        ->assertStatus(202)
        ->assertJsonPath('reply.status', 'completed')
        ->assertJsonPath('reply.content', fn (string $content) => str_starts_with($content, 'Oi, '));

    // A falha fica registrada no log, mas a resposta foi salva (o app busca sozinho).
    Exceptions::assertReported(RuntimeException::class);
});

it('asks for the app token to authorize a channel', function () {
    $leo = User::factory()->create();

    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-user.{$leo->id}"])
        ->assertUnauthorized();
});
