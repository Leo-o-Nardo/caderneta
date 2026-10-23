<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- As poucas páginas do servidor (a raiz, a liberação de cadastro e a exclusão da conta): buscadores não devem guardar. --}}
    <meta name="robots" content="noindex">
    <title>@yield('title') · Caderneta</title>
    <link rel="icon" type="image/png" href="{{ asset('images/caderneta-icone.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    {{-- O guia Caderneta (docs/design-guide.html): papel, tinta, pauta e as letras do app. --}}
    <style>
        :root { --papel: #F4F0E8; --folha: #FBF9F4; --pressionado: #ECE6DA; --pauta: #DCD5C6; --borda: #8F8778; --tinta: #1E1F1C; --grafite: #5F5B52;
                --musgo: #2C5440; --atencao: #7F5A0C; --tijolo: #A63A25;
                --serif: 'Fraunces', Georgia, serif; --sans: 'IBM Plex Sans', 'Segoe UI', system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: var(--papel); color: var(--tinta); font: 400 16px/1.55 var(--sans); -webkit-font-smoothing: antialiased; }
        main { max-width: 420px; margin: 0 auto; padding: 48px 24px 40px; }
        .brand { display: flex; align-items: center; gap: 10px; padding-bottom: 12px; border-bottom: 1px solid var(--tinta); }
        .brand img { width: 28px; height: 28px; }
        .brand span { font: 600 20px/26px var(--serif); }
        .overline { margin: 32px 0 0; font: 600 11px/16px var(--sans); letter-spacing: .08em; text-transform: uppercase; color: var(--grafite); }
        h1 { margin: 6px 0 0; font: 600 28px/34px var(--serif); letter-spacing: -.01em; overflow-wrap: anywhere; }
        .muted { margin: 4px 0 0; color: var(--grafite); overflow-wrap: anywhere; }
        .icon { margin-top: 40px; width: 32px; height: 32px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
        .icon + h1 { margin-top: 12px; }
        .icon.approved { color: var(--musgo); } .icon.rejected { color: var(--tijolo); } .icon.waiting { color: var(--grafite); }
        .rows { margin-top: 24px; border-top: 1px solid var(--pauta); }
        .row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--pauta); }
        .row span:first-child { color: var(--grafite); }
        .row span:last-child { text-align: right; overflow-wrap: anywhere; }
        .tag { font: 600 11px/14px var(--sans); letter-spacing: .08em; text-transform: uppercase; border: 1px solid currentColor; border-radius: 2px; padding: 2px 6px; }
        .tag.pending { color: var(--atencao); } .tag.approved { color: var(--musgo); } .tag.rejected { color: var(--tijolo); }
        form { margin: 0; }
        .primary { margin-top: 32px; width: 100%; min-height: 48px; border: 0; border-radius: 6px; background: var(--tinta); color: var(--papel);
                   display: flex; align-items: center; justify-content: center; gap: 8px; font: 600 15px/1 var(--sans); cursor: pointer; }
        .primary:hover { background: #000; }
        .primary:active { transform: translateY(1px); }
        .primary svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
        .danger-link { display: block; margin: 20px auto 0; padding: 12px; border: 0; background: none; color: var(--tijolo);
                       font: 500 15px/20px var(--sans); text-decoration: underline; text-underline-offset: 4px; cursor: pointer; }
        .primary:focus-visible, .danger-link:focus-visible, footer a:focus-visible { outline: 2px solid var(--musgo); outline-offset: 2px; }
        .caption { margin: 24px 0 0; font: 400 13px/18px var(--sans); color: var(--grafite); }
        /* Os campos do guia ("Campos"): rótulo em cima, caixa de Folha com borda, Musgo no foco, Tijolo no erro. */
        .field { display: grid; gap: 8px; margin-top: 20px; }
        .field label { font: 500 14px/20px var(--sans); color: var(--tinta); }
        .field:focus-within label { color: var(--musgo); }
        .field input { width: 100%; height: 48px; padding: 0 14px; background: var(--folha); border: 1px solid var(--borda); border-radius: 6px;
                       color: var(--tinta); font: 400 16px var(--sans); }
        .field input:focus { outline: none; border-color: var(--musgo); box-shadow: inset 0 0 0 1px var(--musgo); }
        .field.invalid input { border-color: var(--tijolo); box-shadow: inset 0 0 0 1px var(--tijolo); }
        .error { margin: 0; font: 400 13px/18px var(--sans); color: var(--tijolo); }
        .primary.danger { background: var(--tijolo); }
        .primary.danger:hover { background: #8A2F1E; }
        .points { margin: 24px 0 0; padding: 0; list-style: none; border-top: 1px solid var(--pauta); }
        .points li { padding: 12px 0; border-bottom: 1px solid var(--pauta); }
        .points strong { font-weight: 600; }
        footer { margin-top: 48px; padding-top: 12px; border-top: 1px solid var(--pauta); font: 400 13px/18px var(--sans); color: var(--grafite); }
        footer a { color: var(--grafite); text-underline-offset: 3px; }
        /* Os textos longos (a política de privacidade): coluna mais larga, títulos de seção e listas. */
        main.long { max-width: 640px; }
        h2 { margin: 32px 0 0; font: 600 20px/26px var(--serif); }
        .long p { margin: 8px 0 0; }
        .long ul { margin: 8px 0 0; padding-left: 20px; }
        .long li + li { margin-top: 4px; }
        .long a { color: var(--musgo); text-underline-offset: 3px; }
        code { font: 500 .9em/1 ui-monospace, Consolas, monospace; background: var(--pressionado); padding: 2px 5px; border-radius: 2px; }
    </style>
</head>
<body>
    <main class="@yield('width')">
        <div class="brand"><img src="{{ asset('images/caderneta-icone.png') }}" alt=""><span>Caderneta</span></div>
        @yield('content')
        {{-- Os dois endereços que as lojas pedem, em todas as páginas. --}}
        <footer><a href="{{ route('privacy') }}">Política de privacidade</a> · <a href="{{ route('account.delete') }}">Excluir conta</a></footer>
    </main>
</body>
</html>
