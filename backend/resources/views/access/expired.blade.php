@extends('access.layout')

@section('title', 'Link vencido')

@section('content')
    <svg class="icon waiting" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    <h1>Este link venceu</h1>
    <p class="muted">Os links de liberação valem {{ config('access.review_link_days') }} dias, e os botões desta página, 30 minutos depois de abri-la. Um link copiado pela metade também cai aqui.</p>
    <p class="caption">Para ver quem espera liberação e decidir, use o terminal do servidor: <code>php artisan users:pending</code>, depois <code>users:approve</code> ou <code>users:reject</code> com o e-mail.</p>
@endsection
