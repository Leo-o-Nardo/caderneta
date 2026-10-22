# 1. Estrutura do repositório

O repositório tem duas aplicações que conversam pela rede (a API Laravel em `backend/` e o app Expo em `mobile/`), mais a infraestrutura para rodar a API (`docker/` e `docker-compose.yml`), a documentação (`docs/`) e um gerador de imagens da marca (`scripts/`).

## O mapa

```text
.
├── docker-compose.yml        os 8 serviços do ambiente de desenvolvimento (abaixo)
├── docker/                   como montar cada serviço
│   ├── php/Dockerfile        a imagem PHP 8.4 usada pela API, pela fila, pela agenda e pelo Reverb
│   ├── nginx/default.conf    recebe o HTTP na porta 8100 e entrega ao PHP-FPM
│   └── postgres/init.sql     cria o banco dos testes (home_finance_test) ao lado do principal
├── .github/workflows/ci.yml  a CI: testes e estilo do backend, tipos, lint e testes do app, e a marca
├── scripts/brand-icons.mjs   gera o ícone, a splash e o desenho da marca a partir da fonte Fraunces
├── docs/                     guia de design, ícone, arquitetura (publicados no GitHub Pages) e esta pasta
├── backend/                  a API (Laravel 13)
└── mobile/                   o app (Expo SDK 57, React Native 0.86)
```

## Os serviços que rodam juntos

O `docker-compose.yml` sobe a API em partes que rodam separadas, todas a partir da mesma imagem e do mesmo código (a pasta `backend/` é montada em todas):

| Serviço | Comando | Para que existe |
|---|---|---|
| `app` | PHP-FPM | Atende as requisições da API, que chegam pelo `nginx`. |
| `nginx` | servidor web (porta 8100) | A porta de entrada HTTP. Repassa para o `app`. |
| `queue` | `php artisan queue:listen --queue=assistant,default --tries=1 --timeout=120` | Executa o que não pode travar a requisição: a resposta do assistente, a transcrição da voz, os e-mails. A fila `assistant` vem primeiro. |
| `scheduler` | `php artisan schedule:work` | Roda as rotinas da agenda (`backend/routes/console.php`) na hora certa: receitas fixas, avisos, débito automático. |
| `reverb` | `php artisan reverb:start` (porta 8180) | O servidor de WebSocket: entrega ao app as respostas ao vivo e os avisos. |
| `postgres` | PostgreSQL 16 (porta 5433) | O banco. Guarda tudo. |
| `redis` | Redis 7 | A fila de trabalhos e o cache. |
| `mailpit` | porta 8025 | Captura os e-mails em desenvolvimento, para ver sem mandar de verdade. |

> **Para explicar:** "A API não é um programa só. Uma parte responde as requisições, outra executa o trabalho demorado em segundo plano, outra dispara as rotinas do relógio e outra mantém a conexão ao vivo com o celular. Todas usam o mesmo código e o mesmo banco."

## backend/: a API

```text
backend/
├── bootstrap/app.php        ponto de entrada: rotas, canais, middlewares e erros
├── routes/
│   ├── api.php              todas as rotas /api/v1, agrupadas pelo que exigem
│   ├── channels.php         quem pode escutar cada canal do WebSocket
│   ├── console.php          a agenda das rotinas
│   └── web.php              a página de aprovação de cadastro (link do e-mail)
├── config/                  o padrão do Laravel, mais access.php (liberação) e assistant.php (IA)
├── app/
│   ├── Http/
│   │   ├── Middleware/      ResolveCurrentHousehold (a casa atual) e EnsureCanWrite (só leitura)
│   │   ├── Requests/        a validação de cada entrada, por assunto
│   │   ├── Controllers/     recebem a requisição e chamam um service
│   │   └── Resources/       o formato do JSON de resposta
│   ├── Services/            as regras de negócio (16 classes)
│   ├── Support/             peças de cálculo sem banco: Money, Month, InvoicePeriod, HouseholdContext…
│   ├── Models/              20 modelos, um por tabela; Concerns/ tem os filtros da casa e da visibilidade
│   ├── Policies/            quem pode alterar o quê
│   ├── Enums/               os valores fixos: papéis, tipos de pagamento, status
│   ├── Assistant/           o assistente: provedor de IA, ferramentas, ações, cofre, voz
│   ├── Jobs/                o que roda na fila (ProcessTurn, TranscribeVoice, CheckBudgetAlerts)
│   ├── Events/              o que vai ao vivo para o app (respostas, avisos)
│   ├── Alerts/              os avisos: contas, lembretes, orçamentos, resumo da semana
│   ├── Console/Commands/    os comandos artisan (rotinas e ferramentas de linha de comando)
│   ├── Mail/                o e-mail no estilo Caderneta (CadernetaMail)
│   ├── Notifications/       os e-mails de acesso e de senha
│   └── Providers/           AppServiceProvider: qual IA usar, limites de requisição
├── database/
│   ├── migrations/          as 21 mudanças do banco, em ordem
│   ├── factories/           dados falsos para os testes
│   └── seeders/             a casa de demonstração
├── resources/views/         os e-mails e as páginas de aprovação
├── lang/pt_BR/              as mensagens em português (validação, erros, avisos)
└── tests/                   295 testes (Pest): Feature (pela API) e Unit
```

Duas pastas merecem atenção porque não são do padrão do Laravel:

- **`app/Support/`** guarda peças de cálculo que não dependem do banco, como o mês no fuso da pessoa (`Month`), o período de uma fatura (`InvoicePeriod`, `InvoiceCalendar`) e o contexto da casa (`HouseholdContext`, explicado no [backend](02-backend.md#a-casa-como-filtro)).
- **`app/Assistant/`** é um subsistema inteiro, com 61 arquivos. É o assunto da próxima etapa desta documentação.

## mobile/: o app

```text
mobile/
├── app.json                 nome, ícone, splash, esquema de link e experimentos (React Compiler)
├── eslint.config.js         o lint (config do Expo)
├── tsconfig.json            TypeScript; o atalho @/ aponta para src/
├── assets/                  ícone, splash e a marca em SVG (gerados pelo scripts/brand-icons.mjs)
└── src/
    ├── app/                 as telas: cada arquivo é uma rota (Expo Router)
    │   ├── _layout.tsx      ponto de entrada: fontes, sessão, abertura e rotas protegidas
    │   ├── (auth)/          sem login: capa, entrar, criar conta, esqueci a senha
    │   ├── (app)/           com login: as abas e as telas por cima delas
    │   │   ├── (tabs)/      Início, Chat, Resumo, Agenda e Lista
    │   │   └── …            lançamentos, contas, cartões, listas, configurações, primeiros passos
    │   └── convite/[code]   o link do convite, com ou sem login
    ├── api/                 uma função ou hook por recurso da API, com o cache (TanStack Query)
    ├── components/          pedaços de tela; ui/ são os componentes do guia; tour/ e cards/ (fichas do chat)
    ├── lib/                 regras puras (datas, dinheiro, faturas, tour) e as conexões: api.ts, realtime.ts
    ├── stores/              estado local que não vem da API (Zustand): sessão, confirmação, chat ao vivo, tour
    └── theme/               tokens.ts (cores, espaços, fontes do guia) e brand.ts (gerado)
```

Os parênteses em `(auth)`, `(app)` e `(tabs)` são **grupos** do Expo Router: organizam as telas e dão a elas um layout comum, sem aparecer no endereço. A tela `app/(app)/(tabs)/summary.tsx` é aberta como `/summary`.

## Os pontos de entrada

| Onde | O que acontece ali |
|---|---|
| `backend/bootstrap/app.php` | Liga as rotas e os canais (com a autenticação do app, Sanctum), registra os middlewares `household` e `household.write` e faz toda resposta de erro da API sair em JSON. |
| `backend/routes/api.php` | Todas as rotas da API, aninhadas pelo que exigem: nada (entrar, cadastrar), login (`auth:sanctum`), casa atual (`household`) e permissão de escrita (`household.write`). |
| `backend/routes/console.php` | A agenda: o que roda de minuto em minuto ou de hora em hora. |
| `backend/app/Providers/AppServiceProvider.php` | Decide qual IA e qual transcritor usar (pelo `.env`), monta as ferramentas do assistente e define os limites de requisição por minuto. |
| `mobile/src/app/_layout.tsx` | Carrega as fontes, lê o login salvo (`useSession().restore`), toca a abertura e mostra as telas de entrada ou o app, conforme haja login. |
| `mobile/src/app/(app)/_layout.tsx` | Para quem está logado: abre a conexão ao vivo, liga os eventos do chat e monta o tour por cima de tudo. |

## As dependências e por que cada uma está aqui

O projeto usa poucas bibliotecas de propósito; a maior parte das regras é código próprio.

**Backend** (`backend/composer.json`)

| Pacote | Para quê |
|---|---|
| `laravel/framework` 13 | A base: rotas, validação, banco (Eloquent), fila, agenda, e-mail, eventos. |
| `laravel/sanctum` | O login do app: um token por aparelho, guardado no banco e revogável. |
| `laravel/reverb` | O servidor de WebSocket, no protocolo Pusher. |
| `pestphp/pest` (dev) | Os testes, numa sintaxe mais curta que a do PHPUnit. |
| `laravel/pint` (dev) | O formatador de código PHP; a CI recusa código fora do padrão. |

O Gemini é chamado direto pelo cliente HTTP do Laravel (`Http::`), sem SDK, em `app/Assistant/Llm/GeminiProvider.php` e `app/Assistant/Voice/GeminiTranscriber.php`.

**App** (`mobile/package.json`)

| Pacote | Para quê |
|---|---|
| `expo`, `react-native` 0.86, `react` 19 | A base do app. |
| `expo-router` | Navegação por arquivos: cada arquivo em `src/app` é uma tela. |
| `@tanstack/react-query` | Busca os dados da API, guarda em cache, mostra carregando e erro, e busca de novo quando algo muda. |
| `zustand` | O estado que não vem da API (sessão, confirmação, resposta ao vivo, tour). |
| `react-native-reanimated` e `react-native-worklets` | As animações (abertura, barras, tour), rodando fora da thread do JavaScript. |
| `react-native-svg` | Desenho vetorial: o ícone na abertura, o check animado das fichas, o recorte do tour. |
| `expo-audio` | Gravar a mensagem de voz (m4a, mono, 16 kHz). |
| `expo-secure-store` | Guardar o token de login criptografado pelo sistema. |
| `expo-splash-screen`, `expo-font`, `@expo-google-fonts/*` | A splash e as fontes do guia (Fraunces e IBM Plex Sans). |
| `lucide-react-native` | Os ícones de traço fino do guia. |
| `date-fns` | Datas: nomes de mês, "hoje", intervalos. |
| `jest-expo`, `eslint-config-expo`, `typescript` (dev) | Testes, lint e tipos. |

Não há biblioteca de WebSocket: o app fala o protocolo Pusher com um cliente próprio (`src/lib/realtime.ts` e `src/lib/pusherProtocol.ts`), explicado no [App](03-app.md#tempo-real).

> **Para explicar:** "São dois projetos num repositório só: a API, em Laravel, e o app, em React Native. A API roda em várias partes (atendimento, fila, agenda e WebSocket) com o mesmo código. O app é organizado por telas, e cada tela pega os dados por uma camada de API com cache, sem falar com a rede diretamente."
