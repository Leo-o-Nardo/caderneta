{{-- A versão em texto puro do mesmo e-mail (App\Mail\CadernetaMail): sem HTML, então sem escapar. --}}
@if ($overline)
{!! mb_strtoupper($overline['text']) !!}
@endif
{!! $title !!}

@foreach ($paragraphs as $paragraph)
{!! $paragraph !!}

@endforeach
@foreach ($rows as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
@if ($rows)

@endif
@if ($notice)
{!! $notice['lead'] !!} {!! $notice['text'] !!}

@endif
@if ($code)
{!! $code !!}

@endif
@if ($button)
{!! $button['label'] !!}: {!! $button['url'] !!}

@endif
@if ($noteText)
{!! $noteText !!}

@endif
--
{!! $reason !!}
Caderneta · Sua casa em ordem. Sem complicação.
