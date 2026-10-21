<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessService;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * A página que o link do e-mail de cadastro abre (só o administrador recebe esse link). As rotas
 * são assinadas e têm prazo: sem a assinatura, ninguém abre nem decide.
 *
 * Abrir a página não muda nada: só os botões dela liberam ou recusam. O Gmail e os antivírus abrem
 * os links dos e-mails para conferir, e um link que liberasse direto liberaria sem ninguém clicar.
 */
class AccessApprovalController extends Controller
{
    public function review(User $user): View
    {
        // Os botões levam a links assinados próprios, com prazo curto.
        $decide = fn (string $decision) => URL::temporarySignedRoute(
            'access.decide',
            now()->addMinutes(30),
            ['user' => $user->id, 'decision' => $decision],
        );

        return view('access.review', [
            'user' => $user,
            'status' => $user->accessStatus(),
            'approveUrl' => $decide('liberar'),
            'rejectUrl' => $decide('recusar'),
        ]);
    }

    public function decide(User $user, string $decision, AccessService $access): View
    {
        $decision === 'liberar' ? $access->approve($user) : $access->reject($user);

        return view('access.done', ['user' => $user->refresh()]);
    }
}
