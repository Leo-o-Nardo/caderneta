@extends('access.layout')

@section('title', 'Pronto')

@section('content')
    @if ($user->accessStatus() === 'approved')
        <svg class="icon approved" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
        <h1>Acesso liberado</h1>
        <p class="muted">{{ $user->name }} recebeu um e-mail e já pode entrar no app.</p>
        <div class="rows">
            <div class="row"><span>Situação</span><span class="tag approved">Liberado</span></div>
            <div class="row"><span>E-mail</span><span>{{ $user->email }}</span></div>
        </div>
        <p class="caption">Pode fechar esta página.</p>
    @else
        <svg class="icon rejected" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
        <h1>Cadastro recusado</h1>
        <p class="muted">{{ $user->name }} recebeu um aviso educado. O cadastro fica guardado como recusado.</p>
        <div class="rows">
            <div class="row"><span>Situação</span><span class="tag rejected">Recusado</span></div>
            <div class="row"><span>E-mail</span><span>{{ $user->email }}</span></div>
        </div>
        <p class="caption">Mudou de ideia? O link do e-mail continua valendo até o prazo dele: abra de novo e toque em "Liberar acesso".</p>
    @endif
@endsection
