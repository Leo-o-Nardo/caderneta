@extends('access.layout')

@section('title', 'Excluir conta')

{{-- Excluir a conta sem o app (AccountDeletionController). O que sai e o que fica é o do AccountService. --}}
@section('content')
    <p class="overline">Sua conta</p>
    <h1>Excluir a conta</h1>
    <p class="muted">No app, o caminho é Configurações › Conta › Excluir conta. Aqui você faz o mesmo sem o app.</p>

    <ul class="points">
        <li><strong>Sai na hora:</strong> seu login em todos os celulares, suas conversas com o assistente, seus avisos, lembretes e cartões.</li>
        <li><strong>Nas casas que você divide:</strong> os lançamentos, as contas e as listas continuam para as outras pessoas, sem o seu nome. Se você for a única pessoa dona, a casa passa para o membro mais antigo. Se ninguém mais puder editar, a casa é apagada.</li>
        <li><strong>Não dá para desfazer.</strong> Para voltar a usar, é preciso criar uma conta nova.</li>
    </ul>

    <form method="post" action="{{ route('account.delete') }}">
        @csrf
        <div class="field @error('email') invalid @enderror">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required
                   @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror>
            @error('email')<p class="error" id="email-erro">{{ $message }}</p>@enderror
        </div>
        <div class="field @error('password') invalid @enderror">
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   @error('password') aria-invalid="true" aria-describedby="senha-erro" @enderror>
            @error('password')<p class="error" id="senha-erro">{{ $message }}</p>@enderror
        </div>
        <button class="primary danger" type="submit">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
            Excluir minha conta
        </button>
    </form>

    <p class="caption">Esqueceu a senha? No app, use "Esqueci a senha" na tela de entrar para criar outra e volte aqui.</p>
@endsection
