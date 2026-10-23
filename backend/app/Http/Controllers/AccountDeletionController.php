<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * A página /excluir-conta: excluir a conta sem o app. O Google Play exige um endereço público para
 * isso, que vai na ficha do app. Pede o e-mail e a senha, como a tela de entrar; serve também para
 * quem ainda espera a liberação do cadastro.
 */
class AccountDeletionController extends Controller
{
    public function show(): View
    {
        return view('account.delete');
    }

    public function destroy(Request $request, AccountService $accounts): View|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // A mesma mensagem para e-mail desconhecido e senha errada: a página não diz quem tem conta.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }

        $accounts->delete($user);

        return view('account.deleted');
    }
}
