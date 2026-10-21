<?php

use App\Enums\HouseholdRole;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Notifications\AccessApproved;
use App\Notifications\AccessRejected;
use App\Notifications\AccessRequested;
use App\Notifications\MemberJoinedByInvite;
use App\Notifications\PasswordResetCode;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;

// Os e-mails no estilo Caderneta (App\Mail\CadernetaMail): o assunto, o que vai no HTML e a versão em texto.

/** O HTML e o texto puro de um e-mail, como o programa de e-mail recebe. */
function renderedMail(MailMessage $mail): array
{
    return [
        'html' => (string) $mail->render(),
        'text' => view($mail->view[1], $mail->viewData)->render(),
    ];
}

it('asks the administrator to approve, with the person, the link and the terminal way', function () {
    $bia = User::factory()->pending()->create(['name' => 'Bia Souza', 'email' => 'bia@example.com']);
    $mail = (new AccessRequested($bia))->toMail(new AnonymousNotifiable);
    ['html' => $html, 'text' => $text] = renderedMail($mail);

    expect($mail->subject)->toBe('Bia Souza quer entrar na Caderneta')
        ->and($html)->toContain('Esperando liberação')
        ->and($html)->toContain('Bia Souza quer entrar')
        ->and($html)->toContain('bia@example.com')
        ->and($html)->toContain('Liberar ou recusar')
        ->and($html)->toContain("/acesso/{$bia->id}")
        ->and($html)->toContain('<code')
        ->and($html)->toContain('images/caderneta-icone.png')
        ->and($text)->toContain("/acesso/{$bia->id}")
        ->and($text)->toContain('php artisan users:pending')
        ->and($text)->not->toContain('<');
});

it('greets by the first name when the access is approved or rejected', function () {
    $bia = User::factory()->create(['name' => 'Bia Souza']);

    $approved = (new AccessApproved)->toMail($bia);
    $rejected = (new AccessRejected)->toMail($bia);

    expect($approved->subject)->toBe('Seu acesso à Caderneta foi liberado')
        ->and(renderedMail($approved)['html'])->toContain('Seu acesso foi liberado, Bia')
        ->and($rejected->subject)->toBe('Sobre o seu cadastro na Caderneta')
        ->and(renderedMail($rejected)['html'])->toContain('Obrigado pelo interesse, Bia');
});

it('puts the password code in the subject and in the e-mail', function () {
    $leo = User::factory()->create(['name' => 'Leo Ferreira']);
    $mail = (new PasswordResetCode('482913', 15))->toMail($leo);
    ['html' => $html, 'text' => $text] = renderedMail($mail);

    expect($mail->subject)->toBe('Seu código para criar uma nova senha: 482913')
        ->and($html)->toContain('Seu código, Leo')
        ->and($html)->toContain('482913')
        ->and($html)->toContain('15 minutos')
        ->and($text)->toContain('482913');
});

it('tells the administrator who joined by invite, with the house and the role', function () {
    $leo = User::factory()->create(['name' => 'Leo']);
    $house = householdOf($leo);
    $ana = User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@example.com']);
    $invitation = HouseholdInvitation::factory()->for($house)->create(['invited_by' => $leo->id, 'role' => HouseholdRole::Member]);

    $mail = (new MemberJoinedByInvite($ana, $invitation))->toMail(new AnonymousNotifiable);
    $html = renderedMail($mail)['html'];

    expect($mail->subject)->toBe('Ana Souza entrou na Caderneta pelo convite de Leo')
        ->and($html)->toContain('Já liberado')
        ->and($html)->toContain('ana@example.com')
        ->and($html)->toContain($house->name)
        ->and($html)->toContain('Membro');
});

it('shows the Caderneta page when the approval link is not valid', function () {
    $bia = User::factory()->pending()->create();

    $this->get("/acesso/{$bia->id}?expires=1&signature=errada")
        ->assertForbidden()
        ->assertSee('Este link venceu')
        ->assertSee('php artisan users:pending');
});
