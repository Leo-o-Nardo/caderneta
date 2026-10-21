@extends('access.layout')

@section('title', 'Liberar acesso')

@section('content')
    @php($labels = ['pending' => 'Esperando', 'approved' => 'Liberado', 'rejected' => 'Recusado'])

    <p class="overline">Liberação de cadastro</p>
    <h1>{{ $user->name }}</h1>
    <p class="muted">{{ $user->email }}</p>

    <div class="rows">
        <div class="row"><span>Situação</span><span class="tag {{ $status }}">{{ $labels[$status] }}</span></div>
        <div class="row"><span>Cadastro</span><span>{{ $user->created_at->timezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i') }}</span></div>
    </div>

    {{-- Só estes botões mudam o acesso; abrir a página não muda nada. --}}
    @if ($status !== 'approved')
        <form method="post" action="{{ $approveUrl }}">
            @csrf
            <button class="primary" type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                Liberar acesso
            </button>
        </form>
    @endif

    @if ($status !== 'rejected')
        <form method="post" action="{{ $rejectUrl }}">
            @csrf
            <button class="danger-link" type="submit">Recusar cadastro</button>
        </form>
    @endif

    <p class="caption">Ao liberar, a pessoa recebe um e-mail e já consegue entrar. Ao recusar, recebe um aviso educado, e o cadastro fica guardado: dá para liberar depois por este mesmo link.</p>
@endsection
