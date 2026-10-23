@extends('access.layout')

@section('title', 'Finanças da casa')

{{-- A raiz do domínio: quem abrir o endereço no navegador vê o que é a Caderneta. --}}
@section('content')
    <p class="overline">Finanças da casa</p>
    <h1>Sua casa em ordem. Sem complicação.</h1>
    <p class="muted">Fale o gasto e o assistente lança. Receitas, despesas, contas e listas da casa, organizadas num app só.</p>
    <p class="caption">Em breve na Play Store e na App Store.</p>
    {{-- A página de suporte das lojas: precisa de um contato. --}}
    @if ($contact = config('access.contact_email'))
        <p class="caption">Dúvidas ou problemas com o app: <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>
    @endif
@endsection
