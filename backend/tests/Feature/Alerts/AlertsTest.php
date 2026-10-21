<?php

use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\LlmProvider;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Llm\LlmResponse;
use App\Events\Alerts\AlertCreated;
use App\Events\Assistant\AssistantCompleted;
use App\Events\Assistant\AssistantDelta;
use App\Events\Assistant\AssistantStatus;
use App\Models\Alert;
use App\Models\Household;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/*
| Os avisos no Chat (fase 7). 7a: lembretes na hora, contas na véspera e no dia, débito automático,
| os botões dos cards e o número da aba. 7b: orçamento em 80% e 100%, resumo da semana e
| Configurações › Avisos.
*/

// Sexta-feira, 09/10/2026, às 8h de Brasília: antes dos avisos de conta (que começam às 9h).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 08:00', 'America/Sao_Paulo'));
    Event::fake([AlertCreated::class, AssistantStatus::class, AssistantDelta::class, AssistantCompleted::class]);
});

/** Roda uma rotina no momento $at (horário de Brasília), como o agendador faria. */
function runAlertsAt(string $at, string $command): void
{
    test()->travelTo(CarbonImmutable::parse($at, 'America/Sao_Paulo'));
    test()->artisan($command)->assertSuccessful();
}

/** A mensagem do aviso mais recente de $kind que $user recebeu. */
function latestAlert(User $user, string $kind): Message
{
    return Message::query()->findOrFail(
        Alert::query()->where('user_id', $user->id)->where('kind', $kind)->latest('id')->firstOrFail()->message_id,
    );
}

/** Cadastra uma conta fixa pela API, como $user. */
function billFor(User $user, Household $house, array $data): int
{
    Sanctum::actingAs($user);

    return test()->postJson('/api/v1/bills', array_merge([
        'category_id' => categoryOf($house, 'Contas da casa')->id,
        'due_day' => 10,
    ], $data))->assertCreated()->json('id');
}

it('reminds at the right time, once, only who should know', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($leo);

    $this->postJson('/api/v1/reminders', ['text' => 'Pagar o IPVA', 'remind_at' => '2026-10-09 09:00'])->assertCreated();
    $this->postJson('/api/v1/reminders', ['text' => 'Trocar o filtro', 'remind_at' => '2026-10-09 09:00', 'visibility' => 'household'])->assertCreated();

    runAlertsAt('2026-10-09 08:59', 'alerts:reminders');
    expect(Alert::query()->count())->toBe(0);

    runAlertsAt('2026-10-09 09:00', 'alerts:reminders');
    runAlertsAt('2026-10-09 09:01', 'alerts:reminders');

    // O pessoal só para o Leo; o da casa para os dois. Rodar de novo não repete.
    expect(Alert::query()->where('user_id', $leo->id)->count())->toBe(2)
        ->and(Alert::query()->where('user_id', $ana->id)->count())->toBe(1);
    Event::assertDispatchedTimes(AlertCreated::class, 3);

    $message = latestAlert($ana, 'reminder');

    expect($message->role->value)->toBe('event')
        ->and($message->content)->toBe('Lembrete: Trocar o filtro')
        ->and($message->cards()[0]['type'])->toBe('reminder_due')
        ->and($message->cards()[0]['reminder']['remind_at'])->toBe('2026-10-09T09:00:00-03:00');
});

it('handles the reminder buttons: one more hour, done and tomorrow', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    $ipva = $this->postJson('/api/v1/reminders', ['text' => 'Pagar o IPVA', 'remind_at' => '2026-10-09 09:00'])->json('id');
    $filtro = $this->postJson('/api/v1/reminders', ['text' => 'Trocar o filtro', 'remind_at' => '2026-10-09 09:00'])->json('id');
    runAlertsAt('2026-10-09 09:00', 'alerts:reminders');

    $ipvaAlert = Message::query()->findOrFail(Alert::query()->where('key', 'like', "reminder:{$ipva}:%")->firstOrFail()->message_id);
    $filtroAlert = Message::query()->findOrFail(Alert::query()->where('key', 'like', "reminder:{$filtro}:%")->firstOrFail()->message_id);

    // "Adiar 1 h": avisa de novo às 10h.
    $this->postJson("/api/v1/alerts/{$ipvaAlert->id}/snooze_hour")
        ->assertOk()
        ->assertJsonPath('message.cards.0.state', 'snoozed')
        ->assertJsonPath('message.cards.0.snoozed_until', '2026-10-09T10:00:00-03:00');

    runAlertsAt('2026-10-09 10:00', 'alerts:reminders');
    $again = latestAlert($leo, 'reminder');

    expect($again->id)->not->toBe($ipvaAlert->id);

    // "Feito" no aviso novo conclui o lembrete; no antigo, só mostra que já foi resolvido.
    $this->postJson("/api/v1/alerts/{$again->id}/done")->assertOk()->assertJsonPath('message.cards.0.state', 'done');
    $this->postJson("/api/v1/alerts/{$ipvaAlert->id}/done")->assertOk()->assertJsonPath('message.cards.0.state', 'done');
    $this->assertDatabaseMissing('reminders', ['id' => $ipva, 'done_at' => null]);

    // "Amanhã": às 9h do dia seguinte (12h em UTC).
    $this->postJson("/api/v1/alerts/{$filtroAlert->id}/tomorrow")->assertOk()->assertJsonPath('message.cards.0.state', 'snoozed');
    $this->assertDatabaseHas('reminders', ['id' => $filtro, 'remind_at' => '2026-10-10 12:00:00']);
});

it('refuses the buttons of someone else and of a viewer', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $bia = User::factory()->create();
    householdOf($leo, [[$ana, 'member'], [$bia, 'viewer']]);
    Sanctum::actingAs($leo);
    $this->postJson('/api/v1/reminders', ['text' => 'Trocar o filtro', 'remind_at' => '2026-10-09 09:00', 'visibility' => 'household']);
    runAlertsAt('2026-10-09 09:00', 'alerts:reminders');

    Sanctum::actingAs($ana);
    $this->postJson('/api/v1/alerts/'.latestAlert($leo, 'reminder')->id.'/done')->assertNotFound();

    Sanctum::actingAs($bia);
    $this->postJson('/api/v1/alerts/'.latestAlert($bia, 'reminder')->id.'/done')->assertForbidden();
});

it('warns about a bill on the eve and on the day, from 9h, to who can pay', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $bia = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member'], [$bia, 'viewer']]);
    billFor($leo, $house, ['name' => 'Internet', 'expected_cents' => 9990]);
    $agua = billFor($leo, $house, ['name' => 'Água', 'amount_varies' => true, 'expected_cents' => 6000]);

    runAlertsAt('2026-10-09 08:30', 'alerts:bills');
    expect(Alert::query()->count())->toBe(0);

    runAlertsAt('2026-10-09 09:05', 'alerts:bills');
    runAlertsAt('2026-10-09 10:05', 'alerts:bills');

    // Véspera: dono e membro recebem as duas; quem é só leitura, nenhuma. Sem repetir.
    expect(Alert::query()->where('user_id', $leo->id)->pluck('key')->all())
        ->toEqualCanonicalizing(["bill:{$agua}:2026-10:eve", 'bill:'.($agua - 1).':2026-10:eve'])
        ->and(Alert::query()->where('user_id', $ana->id)->count())->toBe(2)
        ->and(Alert::query()->where('user_id', $bia->id)->count())->toBe(0)
        ->and(Message::query()->where('role', 'event')->pluck('content')->unique()->values()->all())
        ->toEqualCanonicalizing(['Internet vence amanhã (R$ 99,90).', 'Água vence amanhã (~R$ 60,00).']);

    // No dia, a Água já foi paga cedo: só a Internet, ainda em aberto, avisa de novo.
    $this->travelTo(CarbonImmutable::parse('2026-10-10 08:00', 'America/Sao_Paulo'));
    Sanctum::actingAs($leo);
    $this->postJson("/api/v1/bills/{$agua}/pay", [
        'month' => '2026-10', 'amount_cents' => 6230, 'paid_on' => '2026-10-10', 'payment_type' => 'pix',
    ])->assertOk();

    runAlertsAt('2026-10-10 09:10', 'alerts:bills');

    expect(latestAlert($leo, 'bill_due')->content)->toBe('Internet vence hoje (R$ 99,90).')
        ->and(Alert::query()->where('key', "bill:{$agua}:2026-10:day")->exists())->toBeFalse();
});

it('handles the bill buttons: remind tomorrow and paid', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    $internet = billFor($leo, $house, ['name' => 'Internet', 'expected_cents' => 9990]);
    runAlertsAt('2026-10-09 09:05', 'alerts:bills');
    $alert = latestAlert($leo, 'bill_due');
    Sanctum::actingAs($leo);

    // "Lembrar amanhã": um lembrete pessoal para amanhã às 9h.
    $this->postJson("/api/v1/alerts/{$alert->id}/remind_tomorrow")->assertOk()->assertJsonPath('message.cards.0.state', 'reminded');
    $this->assertDatabaseHas('reminders', ['text' => 'Pagar Internet', 'remind_at' => '2026-10-10 12:00:00', 'created_by' => $leo->id, 'visibility' => 'private']);

    // "Paga" só depois de pagar de verdade (o app chama depois da tela de pagar).
    $this->postJson("/api/v1/alerts/{$alert->id}/paid")->assertJsonValidationErrors(['action' => 'ainda está em aberto']);

    $this->postJson("/api/v1/bills/{$internet}/pay", [
        'month' => '2026-10', 'amount_cents' => 9990, 'paid_on' => '2026-10-09', 'payment_type' => 'pix',
    ])->assertOk();

    $this->postJson("/api/v1/alerts/{$alert->id}/paid")->assertOk()->assertJsonPath('message.cards.0.state', 'paid');
});

it('launches an autopay bill on the due day, as debit, and an undo is for good', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    billFor($leo, $house, ['name' => 'Condomínio', 'expected_cents' => 62000, 'autopay' => true]);

    // Débito automático não tem aviso de véspera: ele avisa quando lança.
    runAlertsAt('2026-10-09 09:30', 'alerts:bills');
    runAlertsAt('2026-10-09 23:00', 'bills:autopay');
    expect(Alert::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);

    runAlertsAt('2026-10-10 00:30', 'bills:autopay');
    runAlertsAt('2026-10-10 01:30', 'bills:autopay');

    $launched = Transaction::withoutGlobalScopes()->sole();

    expect($launched->amount_cents)->toBe(62000)
        ->and($launched->payment_type->value)->toBe('debit')
        ->and($launched->source->value)->toBe('recurring')
        ->and($launched->created_by)->toBe($leo->id)
        ->and($launched->occurred_on->toDateString())->toBe('2026-10-10')
        ->and(latestAlert($ana, 'autopay')->content)->toBe('Condomínio: R$ 620,00 lançada no débito automático.');

    Sanctum::actingAs($leo);
    $this->postJson('/api/v1/alerts/'.latestAlert($leo, 'autopay')->id.'/undo')->assertOk()->assertJsonPath('message.cards.0.state', 'undone');

    // Desfeito de propósito: a rotina não lança de novo.
    runAlertsAt('2026-10-10 02:30', 'bills:autopay');
    expect(Transaction::withoutGlobalScopes()->count())->toBe(0);
});

it('asks for the amount when the autopay bill changes every month', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    billFor($leo, $house, ['name' => 'Luz', 'amount_varies' => true, 'autopay' => true]);

    runAlertsAt('2026-10-10 08:00', 'bills:autopay');

    $alert = latestAlert($leo, 'autopay');

    expect($alert->content)->toBe('Luz é no débito automático e vence hoje: informe o valor para lançar.')
        ->and($alert->cards()[0]['type'])->toBe('bill_due')
        ->and($alert->cards()[0]['when'])->toBe('autopay')
        ->and(Transaction::withoutGlobalScopes()->count())->toBe(0);
});

it('counts the alerts not seen yet and clears them when the Chat is opened', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    billFor($leo, $house, ['name' => 'Internet', 'expected_cents' => 9990]);
    runAlertsAt('2026-10-09 09:05', 'alerts:bills');
    Sanctum::actingAs($leo);

    $this->getJson('/api/v1/alerts/unread')->assertOk()->assertJsonPath('count', 1);
    $this->postJson('/api/v1/alerts/seen')->assertNoContent();
    $this->getJson('/api/v1/alerts/unread')->assertJsonPath('count', 0);

    runAlertsAt('2026-10-10 09:05', 'alerts:bills');
    Sanctum::actingAs($leo);
    $this->getJson('/api/v1/alerts/unread')->assertJsonPath('count', 1);
});

it('respects who turned a kind of alert off', function () {
    $leo = User::factory()->create(['alert_settings' => ['bill_due' => false]]);
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'member']]);
    billFor($leo, $house, ['name' => 'Internet', 'expected_cents' => 9990]);

    runAlertsAt('2026-10-09 09:05', 'alerts:bills');

    expect(Alert::query()->where('user_id', $leo->id)->count())->toBe(0)
        ->and(Alert::query()->where('user_id', $ana->id)->count())->toBe(1);
});

it('shows the alerts to the assistant, so a "paguei" after a warning makes sense', function () {
    $leo = User::factory()->create();
    $house = householdOf($leo);
    billFor($leo, $house, ['name' => 'Internet', 'expected_cents' => 9990]);
    runAlertsAt('2026-10-09 09:05', 'alerts:bills');

    // Uma IA de mentira que só guarda o que recebeu.
    $spy = new class implements LlmProvider
    {
        public ?LlmRequest $request = null;

        public function generate(LlmRequest $request, ?Closure $onDelta = null): LlmResponse
        {
            $this->request = $request;

            return LlmResponse::text('Certo.');
        }
    };
    app()->instance(LlmProvider::class, $spy);

    Sanctum::actingAs($leo);
    $this->postJson('/api/v1/conversation/messages', ['text' => 'paguei'])->assertStatus(202);

    $texts = array_map(fn (LlmMessage $message) => $message->text, $spy->request->messages);

    expect($texts)->toContain('[aviso do app] Internet vence amanhã (R$ 99,90).');
});

it('warns everyone at 80% and 100% of a budget, once each, with the numbers each one sees', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create();
    $house = householdOf($leo, [[$ana, 'viewer']]);
    Sanctum::actingAs($leo);
    $mercado = categoryOf($house, 'Mercado')->id;
    $this->putJson("/api/v1/budgets/{$mercado}", ['limit_cents' => 100000])->assertSuccessful();

    spend($house, 'Mercado', 50000, '2026-10-02');
    expect(Alert::query()->count())->toBe(0);

    // Chegou a 80%: os dois recebem, até quem só vê. O gasto seguinte (81%) não repete o aviso.
    spend($house, 'Mercado', 30000, '2026-10-05');
    spend($house, 'Mercado', 1000, '2026-10-06');

    expect(Alert::query()->where('kind', 'budget')->pluck('key')->all())
        ->toBe(["budget:{$mercado}:2026-10:80", "budget:{$mercado}:2026-10:80"])
        ->and(latestAlert($ana, 'budget')->content)->toBe('Mercado chegou a 80% do limite do mês: R$ 800,00 de R$ 1.000,00.');

    // Um gasto privado do Leo passa do limite só nos números dele: a Ana continua em 81%.
    spend($house, 'Mercado', 25000, '2026-10-08', extra: ['visibility' => 'private']);

    $message = latestAlert($leo, 'budget');

    expect($message->content)->toBe('Mercado passou do limite do mês: R$ 1.060,00 de R$ 1.000,00.')
        ->and($message->metadata['cards'][0])->toMatchArray([
            'type' => 'budget_alert',
            'month' => '2026-10',
            'threshold' => 100,
            'limit_cents' => 100000,
            'spent_cents' => 106000,
            'percent' => 106,
            'status' => 'over',
        ])
        ->and($message->metadata['cards'][0]['category']['name'])->toBe('Mercado')
        ->and(Alert::query()->where('user_id', $ana->id)->count())->toBe(1);
});

it('checks the budget when an expense is edited, but not for past months nor for who turned it off', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create(['alert_settings' => ['budget' => false]]);
    $house = householdOf($leo, [[$ana, 'member']]);
    Sanctum::actingAs($leo);
    $mercado = categoryOf($house, 'Mercado')->id;
    $this->putJson("/api/v1/budgets/{$mercado}", ['limit_cents' => 100000])->assertSuccessful();

    // Setembro já passou: não conta no orçamento deste mês.
    spend($house, 'Mercado', 120000, '2026-09-20');

    $id = $this->postJson('/api/v1/transactions', [
        'kind' => 'expense',
        'amount_cents' => 90000,
        'description' => 'Compra do mês',
        'occurred_on' => '2026-10-05',
        'category_id' => categoryOf($house, 'Compras')->id,
        'payment_type' => 'pix',
    ])->assertCreated()->json('id');

    expect(Alert::query()->count())->toBe(0);

    // A compra era de mercado: com a categoria trocada, o Mercado chega a 90%. A Ana desligou o aviso.
    $this->patchJson("/api/v1/transactions/{$id}", ['category_id' => $mercado])->assertOk();

    expect(Alert::query()->pluck('key')->all())->toBe(["budget:{$mercado}:2026-10:80"])
        ->and(Alert::query()->value('user_id'))->toBe($leo->id)
        ->and(latestAlert($leo, 'budget')->content)->toBe('Mercado chegou a 90% do limite do mês: R$ 900,00 de R$ 1.000,00.');
});

it('sends the weekly summary on Sunday from 19h, in the time zone and with the numbers of each one', function () {
    $leo = User::factory()->create();
    $ana = User::factory()->create(['timezone' => 'America/Manaus']);
    $house = householdOf($leo, [[$ana, 'member']]);
    billFor($leo, $house, ['name' => 'Luz', 'expected_cents' => 15000, 'due_day' => 22]);
    billFor($leo, $house, ['name' => 'Internet', 'expected_cents' => 9990, 'due_day' => 30]);

    // Domingo, 18/10. A semana vai de segunda (12) a domingo (18); a anterior, de 05 a 11.
    $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00', 'America/Sao_Paulo'));
    Sanctum::actingAs($leo);
    spend($house, 'Mercado', 30000, '2026-10-13');
    spend($house, 'Transporte', 5000, '2026-10-15');
    spend($house, 'Mercado', 40000, '2026-10-06');
    // Domingo, 04/10: duas semanas atrás, fora das contas.
    spend($house, 'Mercado', 99900, '2026-10-04');
    Sanctum::actingAs($ana);
    spend($house, 'Lazer', 10000, '2026-10-17', extra: ['visibility' => 'private']);

    runAlertsAt('2026-10-18 18:59', 'alerts:weekly');
    expect(Alert::query()->count())->toBe(0);

    // 19h em Brasília: só o Leo (em Manaus ainda são 18h).
    runAlertsAt('2026-10-18 19:00', 'alerts:weekly');

    $message = latestAlert($leo, 'weekly');
    $card = $message->metadata['cards'][0];

    expect(Alert::query()->pluck('user_id')->all())->toBe([$leo->id])
        ->and(Alert::query()->value('key'))->toBe("weekly:{$house->id}:2026-W42")
        ->and($message->content)->toBe('Resumo da semana: R$ 350,00 em gastos, 13% a menos que na semana anterior. 1 conta vence nos próximos 7 dias.')
        ->and($card)->toMatchArray([
            'type' => 'weekly_summary',
            'from' => '2026-10-12',
            'to' => '2026-10-18',
            'spent_cents' => 35000,
            'previous_cents' => 40000,
        ])
        ->and(array_column($card['categories'], 'total_cents', 'name'))->toBe(['Mercado' => 30000, 'Transporte' => 5000])
        ->and(array_map(fn (array $bill) => [$bill['bill']['name'], $bill['due_on']], $card['bills']))->toBe([['Luz', '2026-10-22']]);

    // 20h em Brasília, 19h em Manaus: a Ana recebe, com o gasto privado dela. O Leo não recebe de novo.
    runAlertsAt('2026-10-18 20:00', 'alerts:weekly');

    $message = latestAlert($ana, 'weekly');

    expect(Alert::query()->count())->toBe(2)
        ->and($message->content)->toBe('Resumo da semana: R$ 450,00 em gastos, 13% a mais que na semana anterior. 1 conta vence nos próximos 7 dias.')
        ->and(array_column($message->metadata['cards'][0]['categories'], 'total_cents', 'name'))
        ->toBe(['Mercado' => 30000, 'Lazer' => 10000, 'Transporte' => 5000]);
});

it('turns kinds of alert off in the settings, but not the reminders', function () {
    $leo = User::factory()->create();
    householdOf($leo);
    Sanctum::actingAs($leo);

    expect($this->getJson('/api/v1/me')->assertOk()->json('alert_settings'))
        ->toBe(['bill_due' => true, 'autopay' => true, 'budget' => true, 'weekly' => true]);

    $this->patchJson('/api/v1/me', ['alert_settings' => ['weekly' => false]])->assertOk();
    $response = $this->patchJson('/api/v1/me', ['alert_settings' => ['budget' => false]])->assertOk();

    // Cada envio muda só o tipo que veio: o resumo continua desligado.
    expect($response->json('alert_settings'))->toBe(['bill_due' => true, 'autopay' => true, 'budget' => false, 'weekly' => false])
        ->and($leo->fresh()->wantsAlert('weekly'))->toBeFalse();

    $this->patchJson('/api/v1/me', ['alert_settings' => ['reminder' => false]])->assertJsonValidationErrors('alert_settings');
    $this->patchJson('/api/v1/me', ['alert_settings' => ['weekly' => 'talvez']])->assertJsonValidationErrors('alert_settings.weekly');
});
