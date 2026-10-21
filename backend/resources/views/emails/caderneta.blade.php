{{--
    O e-mail no estilo Caderneta (App\Mail\CadernetaMail). Montado como e-mail de verdade: tabelas e
    estilo em cada elemento, para abrir igual no Gmail, no Outlook e no celular. Onde a Fraunces não
    carrega, o título sai em Georgia; onde a IBM Plex Sans não carrega, o texto sai em Helvetica ou Arial.
--}}
@php
    $sans = "'IBM Plex Sans',Helvetica,Arial,sans-serif";
    $serif = 'Fraunces,Georgia,serif';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light">
<title>{{ $subject }}</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#F4F0E8;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F0E8;border-collapse:collapse;">
<tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;border-collapse:collapse;">

    {{-- O topo: o ícone e o nome, com o traço de Tinta embaixo. --}}
    <tr><td style="padding:0 0 12px;border-bottom:1px solid #1E1F1C;">
        <img src="{{ $icon }}" width="28" height="28" alt="" style="vertical-align:middle;border:0;">
        <span style="vertical-align:middle;margin-left:10px;font:600 20px/26px {{ $serif }};color:#1E1F1C;">Caderneta</span>
    </td></tr>

    <tr><td style="padding:32px 0 0;">
        @if ($overline)
            <p style="margin:0 0 6px;font:600 11px/16px {{ $sans }};letter-spacing:1px;text-transform:uppercase;color:{{ $overline['color'] }};">{{ $overline['text'] }}</p>
        @endif
        <h1 style="margin:0;font:600 28px/34px {{ $serif }};color:#1E1F1C;">{{ $title }}</h1>
        @foreach ($paragraphs as $paragraph)
            <p style="margin:12px 0 0;font:400 16px/24px {{ $sans }};color:#1E1F1C;">{{ $paragraph }}</p>
        @endforeach
    </td></tr>

    @if ($rows)
        <tr><td style="padding:24px 0 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font:400 15px/22px {{ $sans }};">
                @foreach ($rows as $label => $value)
                    <tr>
                        <td style="padding:12px 16px 12px 0;border-bottom:1px solid #DCD5C6;{{ $loop->first ? 'border-top:1px solid #DCD5C6;' : '' }}color:#5F5B52;">{{ $label }}</td>
                        <td align="right" style="padding:12px 0;border-bottom:1px solid #DCD5C6;{{ $loop->first ? 'border-top:1px solid #DCD5C6;' : '' }}color:#1E1F1C;font-weight:500;">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </td></tr>
    @endif

    @if ($notice)
        <tr><td style="padding:24px 0 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>
                <td style="border-left:2px solid {{ $notice['color'] }};padding:8px 0 8px 14px;font:400 15px/22px {{ $sans }};color:#5F5B52;"><b style="color:#1E1F1C;font-weight:600;">{{ $notice['lead'] }}</b> {{ $notice['text'] }}</td>
            </tr></table>
        </td></tr>
    @endif

    @if ($code)
        {{-- O código com o traço duplo dos totais. Números da sem serifa: todos da mesma altura, fáceis de copiar. --}}
        <tr><td style="padding:24px 0 0;">
            <div style="display:inline-block;font:600 36px/44px {{ $sans }};font-variant-numeric:lining-nums tabular-nums;letter-spacing:8px;color:#1E1F1C;padding-bottom:6px;border-bottom:4px double #1E1F1C;">{{ $code }}</div>
        </td></tr>
    @endif

    @if ($button)
        <tr><td style="padding:28px 0 0;">
            <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:separate;"><tr>
                <td style="background:#1E1F1C;border-radius:6px;"><a href="{{ $button['url'] }}" style="display:inline-block;padding:14px 24px;font:600 15px/20px {{ $sans }};color:#F4F0E8;text-decoration:none;">{{ $button['label'] }}</a></td>
            </tr></table>
        </td></tr>
    @endif

    @if ($noteHtml)
        <tr><td style="padding:16px 0 0;font:400 13px/18px {{ $sans }};color:#5F5B52;">{{ $noteHtml }}</td></tr>
    @endif

    {{-- O rodapé: por que a pessoa recebeu o e-mail. --}}
    <tr><td style="padding:40px 0 0;border-bottom:1px solid #DCD5C6;"></td></tr>
    <tr><td style="padding:16px 0 0;font:400 13px/18px {{ $sans }};color:#5F5B52;">{{ $reason }}<br>Caderneta · Sua casa em ordem. Sem complicação.</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
