<?php

use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\AssistantStatus;
use App\Models\User;
use App\Notifications\ResponseReported;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
| "Reportar resposta" no Chat: a resposta fica marcada e o administrador recebe, com a pergunta.
| O assistente é o simulado e a fila roda na hora, então a resposta já volta pronta.
*/

beforeEach(function () {
    config(['access.admin_email' => 'admin@example.com']);
    Notification::fake();
    Event::fake([AssistantStatus::class, AssistantDelta::class, AssistantCompleted::class, AssistantFailed::class]);
});

it('marks the answer and sends it to the administrator, once', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    householdOf($leo);
    Sanctum::actingAs($leo);

    $turn = test()->postJson('/api/v1/conversation/messages', ['text' => 'oi'])->assertStatus(202);
    $answer = $turn->json('reply.id');

    test()->postJson("/api/v1/conversation/messages/{$answer}/report")->assertNoContent();
    test()->postJson("/api/v1/conversation/messages/{$answer}/report")->assertNoContent();

    test()->getJson('/api/v1/conversation')
        ->assertJsonPath('data.0.id', $answer)
        ->assertJsonPath('data.0.reported', true)
        ->assertJsonPath('data.1.reported', false);

    Notification::assertSentOnDemandTimes(ResponseReported::class, 1);
    Notification::assertSentOnDemand(
        ResponseReported::class,
        fn (ResponseReported $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'admin@example.com'
            && $notification->answer->id === $answer
            && $notification->question->content === 'oi',
    );
});

it('only reports the assistant answers in the person own conversation', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($leo);

    $turn = test()->postJson('/api/v1/conversation/messages', ['text' => 'oi'])->assertStatus(202);

    // A própria pergunta não é resposta do assistente.
    test()->postJson("/api/v1/conversation/messages/{$turn->json('message.id')}/report")->assertNotFound();

    // A conversa do Leo não é da Ana, mesmo na mesma casa.
    Sanctum::actingAs($ana);
    test()->postJson("/api/v1/conversation/messages/{$turn->json('reply.id')}/report")->assertNotFound();

    Notification::assertNothingSent();
});
