<p align="center"><img src="./assets/icone.png" width="96" alt="Ícone da Caderneta"></p>

<h1 align="center">Caderneta</h1>

<p align="center"><b>Sua casa em ordem. Sem complicação.</b><br>Registre seus gastos, acompanhe suas receitas e despesas e mantenha as contas da sua casa organizadas de forma prática e intuitiva.</p>

<p align="center"><b>Português</b> · <a href="README.en.md">English</a></p>

Um app de finanças da casa para usar falando. A pessoa diz "gastei 42 no almoço e 18 no café, no débito", o assistente entende, monta uma ficha com os lançamentos e espera a confirmação. Por trás, o app cuida de cartões e faturas, contas fixas, orçamentos, listas e lembretes, e avisa antes de uma conta vencer. Tudo pensado para uma casa com várias pessoas, cada uma com o seu papel.

> **O código do app é privado.** Este repositório é a vitrine do projeto: o que o app faz e as decisões de design e de arquitetura. Para conversar sobre o código, fale comigo pelo [LinkedIn](https://www.linkedin.com/in/leonardo-ferreira-de-souza/).

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=flat&logo=postgresql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?style=flat&logo=redis&logoColor=white)
![Reverb](https://img.shields.io/badge/Laravel_Reverb-WebSocket-FF2D20?style=flat&logo=laravel&logoColor=white)
![Expo](https://img.shields.io/badge/Expo-SDK_57-000020?style=flat&logo=expo&logoColor=white)
![React Native](https://img.shields.io/badge/React_Native-0.86-61DAFB?style=flat&logo=react&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?style=flat&logo=typescript&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat&logo=docker&logoColor=white)

<p align="center">
  <img src="./assets/capa.png" width="24%" alt="A capa do app, com o slogan e um exemplo de uso">
  <img src="./assets/inicio.png" width="24%" alt="O Início, com as despesas do mês e o microfone">
  <img src="./assets/chat.png" width="24%" alt="O chat com uma ficha de lançamentos para conferir">
  <img src="./assets/resumo.png" width="24%" alt="O resumo do mês, com o saldo e os orçamentos">
</p>

<p align="center">Documentação: <a href="https://leo-o-nardo.github.io/caderneta/design-guide.html">Guia de design</a> · <a href="https://leo-o-nardo.github.io/caderneta/icone-caderneta.html">O ícone</a> · <a href="https://leo-o-nardo.github.io/caderneta/apresentacao.html">Como funciona por dentro</a></p>

---

## Visão geral

A proposta é manter a simplicidade de anotar, somar e lembrar, mas sem o trabalho de fazer tudo manualmente. Em vez de preencher formulários a cada gasto, basta conversar com o assistente, que registra as informações e ajuda a manter tudo organizado.

Três coisas guiaram o projeto:

- **Falar é o jeito principal de usar.** O microfone fica no centro do Início. A voz vira texto, o texto vira lançamentos, e nada é gravado sem a pessoa conferir.
- **A casa é de várias pessoas.** Cada uma tem o seu login e o seu papel, e escolhe o que é da casa e o que é só dela.
- **O dinheiro precisa estar certo.** Valores em centavos inteiros, datas no fuso de cada pessoa, faturas calculadas pela data de fechamento do cartão e estimativas sempre marcadas como estimativa.

## Design e marca

O visual segue um guia próprio, a **Caderneta**: papel claro, tinta escura, uma pauta fina entre as linhas e números alinhados como num livro-caixa. A cor só aparece quando quer dizer alguma coisa. Dois documentos contam essas decisões em detalhe:

<table>
  <tr>
    <td width="50%" valign="top">
      <a href="https://leo-o-nardo.github.io/caderneta/design-guide.html"><img src="./assets/guia-de-design.png" alt="O começo do guia de design Caderneta, com as decisões aprovadas e o índice"></a>
      <p><b><a href="https://leo-o-nardo.github.io/caderneta/design-guide.html">Guia de design</a></b></p>
      <p>A referência visual do app: toda tela nova ou alterada segue o que está nele, e uma regra só muda depois de mudar no guia. Traz as decisões aprovadas e o porquê de cada uma, a paleta com o contraste medido, a tipografia, espaço e forma, as três telas principais com o porquê de cada escolha, a tela do Plano, o arrastar para ordenar, a área administrativa, os componentes, as micro-interações, as diretrizes para telas novas e o registro de cada versão.</p>
    </td>
    <td width="50%" valign="top">
      <a href="https://leo-o-nardo.github.io/caderneta/icone-caderneta.html"><img src="./assets/icone-caderneta.png" alt="O começo da apresentação do ícone, com ele no iPhone, no Android, no ícone temático, na splash e na tela inicial"></a>
      <p><b><a href="https://leo-o-nardo.github.io/caderneta/icone-caderneta.html">O ícone da Caderneta</a></b></p>
      <p>A apresentação da marca: o ícone em cada lugar onde aparece (iPhone, Android, ícone temático, splash e a tela inicial em tamanho real), a abertura do app tocando, com câmera lenta, as partes do ícone e o porquê deste, o caminho do primeiro esboço até a decisão e como os arquivos são gerados.</p>
    </td>
  </tr>
</table>

Os dois estão publicados no GitHub Pages, junto com o diagrama da arquitetura, na [documentação da Caderneta](https://leo-o-nardo.github.io/caderneta/). Também abrem direto no navegador a partir da pasta `docs/`.

**O ícone:** a capa da caderneta em Musgo, com o elástico de Tinta perto da borda e, escrito nela, o "C" da Fraunces com o traço duplo de quem fecha uma conta.

<p align="center"><img src="./assets/abertura.gif" width="30%" alt="A abertura do app: o ícone vira a capa da caderneta e a capa se abre sobre a tela"></p>

**A abertura:** o ícone da splash cresce até virar a capa; quando o app fica pronto, o elástico é puxado para o lado e a capa se abre sobre a tela. Com login, a capa espera os dados do Início, por até 3 s, para abrir sobre a tela completa. Um toque pula a animação.

**Cores**

| Nome | Cor | Uso |
|---|---|---|
| Papel | `#F4F0E8` | O fundo de todas as telas. |
| Tinta | `#1E1F1C` | Texto principal e botão principal. |
| Grafite | `#5F5B52` | Legendas, datas e detalhes. |
| Musgo | `#2C5440` | Ações, seleção e dinheiro que entra. É a cor da marca. |
| Mostarda e Atenção | `#D9A53A` · `#7F5A0C` | O que pede atenção, como o orçamento a partir de 80%. |
| Tijolo | `#A63A25` | Atrasada, estourou, erro e apagar. |

As categorias usam 14 tons de pigmento (Louro, Ardósia, Ameixa, Telha…), sempre só no ícone da categoria.

**Tipografia:** Fraunces nos títulos e nos valores grandes; IBM Plex Sans no resto. Os valores em R$ usam algarismos de mesma largura, para as colunas alinharem.

**Princípios**
- **Divisória, não caixa:** listas e seções ficam direto no papel, separadas pela pauta. A única caixa é a ficha do chat.
- **Um número principal por tela**, o resto em tamanhos menores.
- **Ícones como num Kindle:** traço fino, sempre com uma palavra ao lado.
- **Formulários com o botão preso embaixo**, sempre à mão, e "Apagar" longe dele, no fim.
- **Confirmações na folha do app**, que diz o que acontece com os números ("Sai dos totais de outubro").
- **Acessibilidade:** contraste de 4,5:1 nos textos, área de toque de 44, leitor de tela em todos os botões de ícone e "Reduzir movimento" respeitado.

## Principais pontos

### Falar e lançar
- **Voz:** toque no microfone, fale (até 1 minuto) e toque de novo para enviar. A transcrição é feita pelo agente de IA e a mensagem aparece no chat.
- **O assistente responde ao vivo:** a resposta chega aos poucos, por WebSocket, enquanto é escrita.
- **Fichas para conferir:** o que o assistente propõe aparece numa ficha editável (valor, categoria, data, forma de pagamento) com "Confirmar" e "Cancelar".
- **Também dá para digitar** no chat, ou lançar à mão pelo formulário.

### O que o assistente sabe fazer
- Lançar gastos e receitas, inclusive compras parceladas no cartão.
- Marcar uma conta como paga, corrigir ou apagar um lançamento.
- Consultar gastos por período, categoria ou cartão, e montar gráficos.
- Criar contas fixas, receitas fixas (como o salário), orçamentos, listas e lembretes.
- Colocar e marcar itens nas listas ("coloca leite e café na lista").
- Não dá conselho de investimento.

### A casa
- **Papéis:** dono, membro e só leitura.
- **Convites** por link e por código de 6 caracteres, válidos por 7 dias.
- **Privado ou da casa:** cada lançamento, lista e lembrete pode ser só de quem criou.
- **Várias casas por pessoa:** a da família e a que divide com quem mora junto, por exemplo.
- **Cartões por pessoa:** cada um tem os seus, e as compras caem no cartão certo.

### Dinheiro
- **Lançamentos** com categoria, forma de pagamento e cartão, filtros por mês e busca.
- **Parcelamentos:** uma compra no passado cria todas as parcelas nas faturas certas.
- **Faturas** por cartão, com fechamento, vencimento e quanto é de parcelas.
- **Contas fixas:** com valor fixo ou variável (a estimativa é a média dos últimos 3 pagamentos), pular um mês, desfazer um pagamento e débito automático, que lança a despesa sozinho no dia.
- **Receitas fixas:** o salário entra sozinho todo mês, no dia certo.
- **Orçamentos** por categoria, com a média dos últimos meses como sugestão.
- **Categorias** com ícone, cor e ordem, que aparecem numa grade na hora de lançar. A ordem muda arrastando pela alça, no modo Organizar.
- **Resumo do mês:** saldo, receitas e despesas, gastos por categoria, quem gastou quanto, faturas e próximas contas.

### Agenda, listas e lembretes
- **Agenda** com contas, cartões e lembretes num lugar só.
- **Lembretes** com repetição ("a cada 6 meses"), da casa ou só seus.
- **Listas** de compras, desejos, coisas a fazer ou do jeito que você quiser, com toque para marcar no mercado. Os itens e as próprias listas se ordenam arrastando.

### Avisos no chat e no celular
- Cada aviso chega no chat e, com as notificações ligadas, também como push no celular. Tocar na notificação abre o chat da casa certa.
- Contas na véspera e no dia do vencimento.
- Débito automático lançado (ou pedindo o valor, quando ele muda todo mês).
- Orçamento chegando a 80% e passando de 100%.
- Resumo da semana, no domingo à noite.
- Lembretes na hora marcada, com "Feito", "Adiar 1 h" e "Amanhã".
- Cada tipo de aviso pode ser desligado.

### Planos
- **Gratuito e Plus:** cada plano tem um limite de uso do assistente por mês, contado a partir do dia em que a conta foi criada. O Plus tem um limite bem maior. Lançar à mão, as listas e a agenda nunca têm limite.
- **O limite é dito em tempo:** o app fala "volta a zero no dia 12", nunca em dinheiro ou tokens.
- **Tela do Plano:** em Configurações › Assistente › Plano, o uso do mês em porcentagem, numa barra que fica em Atenção a partir de 80%, o dia em que volta a zero e os planos, sem preço. O Plus dado diz até quando vale.
- **Aviso no Início:** só a partir de 80% e no limite, no mesmo traço das contas que vencem. O Início não ganha mais um número fixo.
- **Folha do limite:** quando o uso do mês acaba, uma folha mostra quando ele volta e o que continua funcionando, no lugar de uma mensagem de erro.
- **Pedido do Plus pelo WhatsApp:** no limite do Gratuito, a folha e a tela do Plano abrem o WhatsApp do administrador com a mensagem pronta. Ele recebe um e-mail para dar o Plus ou negar.
- **Avaliação na loja:** a janela do próprio sistema, uma vez por aparelho, depois da 5ª ficha confirmada e de 7 dias de uso, e sem recompensa.

### Acesso e segurança
- **Cadastro com liberação:** quem se cadastra sozinho espera o administrador liberar. Ele recebe um e-mail com um link assinado para uma página de aprovação.
- **Convite libera na hora:** quem chega por convite entra direto, e o administrador só é avisado.
- **Esqueci a senha** com código de 6 números por e-mail.
- **Sessão por aparelho**, com "sair de todos os aparelhos".
- **Privacidade antes da IA:** CPF, CNPJ, cartões, chaves Pix, telefones, e-mails, agência e conta são trocados por marcadores antes de qualquer texto chegar ao modelo.
- **Limites:** número de mensagens por pessoa por dia, além do limite do mês de cada plano (veja [Planos](#planos)).

### Área administrativa
Páginas no navegador, em `/admin`, só para o administrador e no mesmo visual do app. Estão desenhadas na [parte do guia sobre a área administrativa](https://leo-o-nardo.github.io/caderneta/design-guide.html#admin).

- **Entrada em dois passos:** e-mail e senha e, num navegador novo, um código de 6 números por e-mail. Depois do código, o navegador fica conhecido por 60 dias.
- **Visão geral:** o que espera uma decisão (cadastros para liberar e pedidos de Plus), quantas pessoas estão no Plus e o custo de hoje e do mês.
- **Pessoas:** busca por nome ou e-mail, filtros e o uso de cada pessoa contra o limite do plano.
- **Plano de cada pessoa:** dar o Plus por 1, 3, 6 ou 12 meses (os meses somam ao Plus que ela já tem), tirar o Plus ou marcar o Plus fixo.
- **Pedidos de Plus:** dar ou negar na própria linha da lista.
- **Uso da IA:** custo por dia ou por mês, por modelo e por pessoa, em dólar ou em reais. A cotação é consultada uma vez por dia e fica guardada em cache, e a última que deu certo cobre uma falha da consulta.
- **É o único lugar com o custo da IA:** no app, ninguém vê valores em dólar.

## Arquitetura

O app **pede pela API** (HTTP) e **escuta pelo Reverb** (WebSocket); tudo que demora (a IA, a transcrição, os e-mails) sai da requisição e roda na fila. O desenho segue o caminho da mensagem de voz ao lançamento registrado:

<p align="center"><a href="https://leo-o-nardo.github.io/caderneta/arquitetura.html"><img src="./assets/arquitetura.png" alt="Diagrama de sequência entre app, Reverb, API, PostgreSQL, fila e agente de IA: ao abrir o app, a conexão WebSocket e a assinatura do canal; depois enviar, transcrever, responder e confirmar, com as chamadas HTTP e os eventos de cada passo."></a></p>

Para ir mais fundo, a página [Como funciona por dentro](https://leo-o-nardo.github.io/caderneta/apresentacao.html) explica a arquitetura para quem não conhece o código: as partes, o caminho de uma requisição e de uma mensagem de voz e o cofre de privacidade.

## Por dentro

### Três jeitos de agir
Nem toda ação do assistente pede o mesmo cuidado, então elas se dividem em três níveis:

| Nível | Exemplos | Como funciona |
|---|---|---|
| Confirmar | lançar gastos, parcelar, pagar conta, corrigir um lançamento | A ficha aparece editável e nada é gravado antes do "Confirmar". |
| Explícito | apagar um lançamento, apagar uma lista | A ficha mostra o impacto ("sai dos totais de outubro") antes de confirmar. |
| Imediato, com desfazer | itens de lista, lembretes, contas fixas, orçamentos | Feito na hora, com "Desfazer" por 2 minutos. |

A decisão importante: **a confirmação nunca volta para a IA**. Quando o modelo propõe uma ação, o app valida os dados com as regras dele e guarda uma ação pendente já pronta para gravar. Ao tocar em "Confirmar", o app grava exatamente o que está na ficha (com as edições da pessoa). O modelo não tem uma segunda chance de inventar um valor na hora de gravar.

### Privacidade antes da IA
Todo texto que vai para a IA, digitado ou transcrito, passa antes por um cofre (`SecureDataVault`):

- CPF e CNPJ são reconhecidos pelos dígitos verificadores, e cartões pelo algoritmo de Luhn, para não confundir com qualquer número comprido.
- Cada dado vira um marcador, como `[CPF_1]` ou `[CARTAO_1]`. O modelo trabalha com o marcador, e a resposta volta com o dado original, inclusive no meio do texto chegando ao vivo.
- O cofre envolve qualquer provedor de IA, então trocar de modelo não abre uma brecha.
- As chamadas de ferramenta são devolvidas ao modelo exatamente como ele as mandou, ainda mascaradas, para não invalidar a assinatura de raciocínio que o provedor exige.
- Os nomes dos membros da casa continuam visíveis: o assistente precisa deles para entender "a Ana pagou a luz".

### O limite pelo custo
O limite do assistente não conta mensagens: conta quanto cada pergunta custou de verdade.

- Cada chamada ao modelo registra os tokens e o custo, pelo preço do modelo usado e com o desconto do cache. Voz e texto somam juntos, no período de cada pessoa.
- O custo de uma pergunta só se sabe depois da resposta. Por isso a última pergunta do mês pode passar um pouco do limite: é melhor do que cortar uma resposta no meio.
- O app recebe só a porcentagem usada e o dia em que o limite volta. O valor em dólar fica no servidor e na área administrativa.
- Uma chave liga a cobrança do limite. Desligada, ninguém é barrado, e o relatório mostra quem passaria do limite, para calibrar os valores antes de valer.

### O design como sistema
O visual segue um guia próprio, a "Caderneta" (veja a seção [Design e marca](#design-e-marca)). Duas partes mostram como ele foi levado a sério:

- **O ícone sai de um script, sem dependências.** `scripts/brand-icons.mjs` lê o contorno do "C" direto do arquivo da fonte Fraunces, monta o ícone, desenha os PNG com um rasterizador próprio e grava também os caminhos que o app usa.
- **A abertura é feita em código, e não em vídeo.** Ela começa exatamente igual à splash do sistema (o mesmo desenho, no mesmo lugar), espera o app ficar pronto sem emenda e termina revelando a tela de verdade. Um vídeo não faria nenhuma das três coisas.

## Stack

| Camada | Tecnologias |
|---|---|
| App | Expo SDK 57, React Native 0.86 (New Architecture), React 19, TypeScript, Expo Router, TanStack Query, Zustand, Reanimated 4, react-native-svg, expo-audio |
| API | PHP 8.4, Laravel 13, Sanctum (token por aparelho), sessão do Laravel na área administrativa, Pest |
| Tempo real | Laravel Reverb (WebSocket) |
| Assistente | Agente de IA com ferramentas (conversa, ações e transcrição de voz), atrás de uma interface de provedor, e um provedor "fake" por regras para desenvolvimento e testes |
| Dados | PostgreSQL 16, Redis 7 (fila e cache) |
| Infra | Docker Compose, Nginx, PHP-FPM, Mailpit |

## Testes e qualidade

O backend tem testes de API, de regras e do assistente (Pest), que rodam num banco separado e sempre com o assistente "fake", para não gastar a chave da IA. O app tem testes das regras (Jest). A cada push, o CI roda os testes dos dois lados, o estilo do PHP (Pint), os tipos, o ESLint e confere se os arquivos da marca estão em dia com o script que os gera.

## API

Todas as rotas ficam abaixo de `/api/v1` e, fora as de entrada, pedem o token do aparelho (`Authorization: Bearer`).

| Grupo | Rotas principais |
|---|---|
| Entrada | `POST /auth/register`, `/auth/login`, `/auth/forgot-password`, `/auth/reset-password`, `/auth/logout`, `/auth/logout-all` |
| Conta | `GET`, `PATCH` e `DELETE /me`, `PUT /me/password` |
| Casa | `/households`, `/household`, `/household/members/{member}`, `/household/invitations`, `/invitations/{code}/accept` |
| Lançamentos | `/transactions`, `/installment-plans`, `/categories`, `PUT /categories/order` |
| Cartões e contas | `/cards`, `GET /cards/{card}/invoice`, `/bills`, `POST /bills/{bill}/pay`, `/bills/{bill}/skip`, `/recurring-incomes` |
| Orçamentos e resumo | `/budgets/{category}`, `GET /summary` |
| Agenda e listas | `/reminders`, `/reminders/{reminder}/done`, `/lists`, `PUT /lists/order`, `/lists/{list}/items`, `PUT /lists/{list}/order`, `/list-items/{item}` |
| Plano | `GET /assistant/usage`, `POST /assistant/plus-request` |
| Assistente | `GET /conversation`, `POST /conversation/messages`, `POST /conversation/voice`, `POST /conversation/messages/{message}/report`, `POST /actions/{action}/confirm`, `/cancel`, `/undo` |
| Avisos | `GET /alerts/unread`, `POST /alerts/seen`, `POST /alerts/{message}/{action}`, `POST /push-tokens` |

## Estrutura do código

Como o repositório privado está organizado:

```text
├── docker-compose.yml          # app, fila, tarefas agendadas, reverb, nginx, postgres, redis, mailpit
├── docker/                     # Dockerfile do PHP, Nginx e o banco de testes
├── scripts/brand-icons.mjs     # gera o ícone, a splash e os caminhos da marca
├── docs/                       # guia de design, ícone, arquitetura, apresentação e a documentação técnica
├── backend/                    # API Laravel
│   ├── app/
│   │   ├── Assistant/          # o assistente: provedores, ferramentas, ações, cofre de privacidade, voz
│   │   ├── Alerts/             # avisos de contas, orçamentos, resumo da semana e lembretes
│   │   ├── Services/           # regras de lançamentos, parcelas, contas, orçamentos, acesso, planos e uso da IA
│   │   ├── Http/               # controllers, requests e resources da API e da área administrativa
│   │   ├── Mail/               # o e-mail no estilo Caderneta
│   │   └── Notifications/      # cadastro, acesso liberado, códigos de senha e de entrada, pedido de Plus
│   ├── database/               # migrations, factories e a casa de demonstração
│   ├── resources/views/        # e-mails, as páginas de aprovação e a área administrativa
│   └── tests/                  # testes de API, regras e assistente (Pest)
└── mobile/                     # app Expo
    ├── assets/                 # ícone, splash e a marca em SVG
    └── src/
        ├── app/                # telas (Expo Router)
        ├── api/                # chamadas à API e cache (TanStack Query)
        ├── components/         # telas em partes, fichas do chat e os componentes do guia (ui/)
        ├── lib/                # regras do app, com testes
        ├── stores/             # sessão, confirmação e estado do chat (Zustand)
        └── theme/              # tokens do guia e o desenho da marca
```

## Limitações e próximos passos

- **Convite:** vai só com o código de 6 caracteres; ainda não há link que abra o app direto.
- **Plus:** ainda não é vendido pelas lojas; quem libera é o administrador, pelo pedido do WhatsApp ou pela área administrativa. A cobrança pelas lojas vem depois.
- **Publicação:** o app está em revisão na Play Store e na App Store. O servidor de produção roda com Docker Compose.

## Autor

**Leonardo Ferreira** · [LinkedIn](https://www.linkedin.com/in/leonardo-ferreira-de-souza/) · [GitHub](https://github.com/Leo-o-Nardo)

## Uso deste material

© 2026 Leonardo Ferreira. Todos os direitos reservados.

O código do app é privado. Os textos, as imagens, o nome Caderneta e o ícone deste repositório estão aqui para leitura e avaliação. Para usar, copiar ou distribuir qualquer parte, fale comigo antes pelo [LinkedIn](https://www.linkedin.com/in/leonardo-ferreira-de-souza/) ou pelo e-mail leonard.ofs351@gmail.com.

As fontes Fraunces e IBM Plex Sans (SIL Open Font License) e os ícones Lucide (ISC) seguem as licenças deles.
