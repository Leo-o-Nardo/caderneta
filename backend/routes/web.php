<?php

use App\Http\Controllers\AccessApprovalController;
use App\Http\Controllers\AccountDeletionController;
use Illuminate\Support\Facades\Route;

// A raiz do domínio: uma página simples sobre o app, no lugar da tela padrão do Laravel.
Route::view('/', 'home');

// A política de privacidade: o endereço vai nas fichas das duas lojas e o app abre em Configurações.
Route::view('privacidade', 'privacy')->name('privacy');

// Excluir a conta sem o app: o endereço vai na ficha do Google Play. No app, é Configurações › Conta.
Route::get('excluir-conta', [AccountDeletionController::class, 'show'])->name('account.delete');
Route::post('excluir-conta', [AccountDeletionController::class, 'destroy'])->middleware('throttle:auth');

// O link do e-mail de cadastro (só o administrador recebe): assinado e com prazo. A página mostra
// quem pediu; só os botões dela (POST, também assinados) liberam ou recusam.
Route::middleware('signed')->prefix('acesso/{user}')->whereNumber('user')->group(function () {
    Route::get('/', [AccessApprovalController::class, 'review'])->name('access.review');
    Route::post('{decision}', [AccessApprovalController::class, 'decide'])
        ->whereIn('decision', ['liberar', 'recusar'])
        ->name('access.decide');
});
