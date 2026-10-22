<?php

use App\Http\Controllers\AccessApprovalController;
use Illuminate\Support\Facades\Route;

// A raiz do domínio: uma página simples sobre o app, no lugar da tela padrão do Laravel.
Route::view('/', 'home');

// O link do e-mail de cadastro (só o administrador recebe): assinado e com prazo. A página mostra
// quem pediu; só os botões dela (POST, também assinados) liberam ou recusam.
Route::middleware('signed')->prefix('acesso/{user}')->whereNumber('user')->group(function () {
    Route::get('/', [AccessApprovalController::class, 'review'])->name('access.review');
    Route::post('{decision}', [AccessApprovalController::class, 'decide'])
        ->whereIn('decision', ['liberar', 'recusar'])
        ->name('access.decide');
});
