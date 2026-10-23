@extends('access.layout')

@section('title', 'Política de privacidade')
@section('width', 'long')

{{--
  A política de privacidade: o endereço vai nas fichas das lojas, e o app abre em Configurações.
  Ela descreve o que o código faz de verdade: ao mudar o que é guardado ou para onde vai, mude aqui
  e a data embaixo do título.
--}}
@section('content')
    @php($contact = config('access.contact_email'))

    <p class="overline">Caderneta</p>
    <h1>Política de privacidade</h1>
    <p class="muted">Atualizada em 23 de outubro de 2026.</p>

    <h2>Quem cuida dos seus dados</h2>
    <p>A Caderneta é mantida por {{ config('access.privacy_controller') }}, responsável pelos dados pessoais tratados no app, nos termos da Lei Geral de Proteção de Dados (Lei 13.709/2018).
        @if ($contact) Para qualquer assunto sobre os seus dados, escreva para <a href="mailto:{{ $contact }}">{{ $contact }}</a>.@endif</p>

    <h2>O que fica guardado</h2>
    <ul>
        <li><strong>Sua conta:</strong> nome, e-mail, senha (guardada de forma que não dá para ler de volta), fuso horário e os avisos que você quer receber.</li>
        <li><strong>As finanças da casa:</strong> lançamentos, contas, receitas fixas, cartões, orçamentos, categorias, listas e lembretes. Dos cartões, só o nome, os dias de fechamento e de vencimento e o limite: o app nunca pede o número.</li>
        <li><strong>As conversas com o assistente:</strong> o texto das mensagens e as fichas. "Limpar conversa" tira a conversa da tela, mas ela continua guardada até a conta ser excluída.</li>
        <li><strong>As mensagens de voz:</strong> o áudio fica no servidor só até virar texto. Depois disso, é apagado, tenha a transcrição dado certo ou não. Fica só o texto.</li>
        <li><strong>Os aparelhos:</strong> o nome do aparelho de cada login e, se você aceitar as notificações, o endereço de push do celular.</li>
        <li><strong>O uso do assistente:</strong> quantas mensagens você mandou e quanto processamento cada uma gastou, para controlar os limites e os custos.</li>
        <li><strong>Registros técnicos:</strong> o servidor registra os acessos, como o endereço IP, para segurança e para investigar erros. Esses registros são sobrescritos sozinhos com o tempo.</li>
    </ul>

    <h2>Para que os dados são usados</h2>
    <p>Para o app funcionar: guardar e mostrar as finanças da casa, responder no Chat, mandar os avisos e os e-mails da conta (a liberação do cadastro e o código para trocar a senha) e proteger o serviço contra abuso. Os dados não são vendidos nem usados para publicidade. O app não tem anúncios nem ferramentas de rastreamento de terceiros.</p>

    <h2>Com quem os dados são compartilhados</h2>
    <ul>
        <li><strong>Google, pela API Gemini:</strong> o texto das perguntas, com o que o assistente precisa saber da casa para responder (categorias, contas, valores, os primeiros nomes de quem mora junto), e o áudio das mensagens de voz, para virar texto. Antes de sair do servidor, o texto passa por um filtro que troca CPF, CNPJ, números de cartão, telefones, e-mails, chaves Pix e dados bancários por marcadores: a IA vê "[CPF_1]", e não o número. O áudio vai como foi gravado. A Caderneta usa a API paga, e, nesses termos, o Google não usa as perguntas e as respostas para treinar os modelos dele.</li>
        <li><strong>Expo e Firebase Cloud Messaging (do Google), e o serviço de notificações da Apple no iPhone:</strong> entregam as notificações, se você aceitar. Eles recebem o título e o texto de cada aviso, que pode citar valores e nomes de contas.</li>
        <li><strong>Gmail (do Google):</strong> envia os e-mails da conta.</li>
        <li><strong>As pessoas da sua casa:</strong> o que é lançado como da casa aparece para quem faz parte dela. O que é marcado como privado, não.</li>
        <li><strong>Autoridades:</strong> só quando a lei obrigar.</li>
    </ul>
    <p>O Google, a Expo e a Apple podem tratar esses dados fora do Brasil, sob as garantias dos contratos de cada um.</p>

    <h2>Por quanto tempo</h2>
    <p>Enquanto a conta existir. Ao excluir a conta, os dados saem na hora. As cópias de segurança do banco de dados guardam o que havia no dia por até 14 dias, e então são apagadas.</p>

    <h2>Segurança</h2>
    <p>O app fala com o servidor só por conexão cifrada (HTTPS). As senhas são guardadas de forma que não dá para ler de volta, cada celular tem o próprio login, e o acesso ao servidor é restrito.</p>

    <h2>Seus direitos</h2>
    <p>Pela LGPD, você pode confirmar se seus dados são tratados, acessá-los, corrigi-los, pedir uma cópia em formato legível, saber com quem são compartilhados e pedir a exclusão.</p>
    <ul>
        <li>O nome e os avisos, você muda no próprio app, em Configurações.</li>
        <li>Para excluir a conta: no app, em Configurações › Conta › Excluir conta, ou sem o app, na página <a href="{{ route('account.delete') }}">Excluir conta</a>.</li>
        @if ($contact)<li>Para o resto, escreva para <a href="mailto:{{ $contact }}">{{ $contact }}</a>. A resposta vem em até 15 dias.</li>@endif
    </ul>

    <h2>Permissões do celular</h2>
    <ul>
        <li><strong>Microfone:</strong> só enquanto você grava uma mensagem de voz.</li>
        <li><strong>Notificações:</strong> opcionais. Dá para ligar e desligar quando quiser, no app (Configurações › Avisos) ou nas configurações do celular.</li>
    </ul>
    <p>O app não pede a sua localização, seus contatos nem suas fotos.</p>

    <h2>Menores de idade</h2>
    <p>A Caderneta é feita para adultos que organizam as finanças da casa e não se destina a menores de 18 anos.</p>

    <h2>Mudanças nesta política</h2>
    <p>Quando esta política mudar, a data no topo muda junto. Mudanças importantes são avisadas no app ou por e-mail.</p>
@endsection
