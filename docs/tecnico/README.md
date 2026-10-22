# Documentação técnica da Caderneta

Como o sistema foi construído, para quem vai mexer no código ou precisa explicá-lo para outra pessoa. A visão geral e a forma de rodar estão no [README do projeto](../../README.md); o visual, no [guia de design](https://leo-o-nardo.github.io/caderneta/design-guide.html). Aqui o assunto é o código: onde cada coisa está, por que está ali e como as partes conversam.

> **Conferido no código do commit `12d7180`** (primeiro acesso: boas-vindas, primeiros passos e tour). Quando o código mudar, a referência ao arquivo e ao nome da classe ou função continua valendo; números de linha não são usados de propósito, porque mudam a cada edição.

## Por onde ler

| # | Documento | O que responde |
|---|---|---|
| 1 | [Estrutura do repositório](01-estrutura.md) | O que tem em cada pasta, os pontos de entrada, as dependências e para que servem. |
| 2 | [Backend](02-backend.md) | O caminho de uma requisição (rota, middleware, validação, controller, service, model), a casa como filtro, autenticação e autorização, erros. |
| 3 | [App](03-app.md) | Telas e navegação, como o app busca e guarda os dados, o estado local, o tempo real e o tratamento de erros. |
| 4 | [O assistente](04-assistente.md) | O turno com a IA, as ferramentas e os níveis de cuidado, as ações pendentes, o cofre de privacidade, a voz, custos e limites. |
| 5 | [Os fluxos, passo a passo](05-fluxos.md) | Cadastro e liberação, entrar, senha, convite, lançar, voz até a ficha, confirmar, avisos, receitas fixas, abertura e primeiro acesso. |
| 6 | [O modelo de dados](06-dados.md) | As 20 tabelas por assunto, as regras que valem para quase todas e o que é calculado na hora em vez de guardado. |

Para apresentar a arquitetura a quem não conhece o código, use a página [Como funciona por dentro](https://leo-o-nardo.github.io/caderneta/apresentacao.html): as mesmas ideias, com desenhos no estilo do guia e o que dizer em cada parte.

## Os limites desta documentação

**Como foi conferida.** Além da leitura do código, um script confere que todo caminho de arquivo, classe e método citado (685 referências nos 7 documentos) existe no repositório, e os números (testes, telas, tabelas, ferramentas) foram contados de novo no código.

**O que foi analisado só pelo padrão, sem descer em cada caso:**

- As 43 telas e os 76 componentes do app, um por um. Os documentos explicam como eles se organizam e se comunicam, não cada tela.
- A lógica interna de cada uma das 19 ferramentas do assistente. Está documentado o contrato que todas seguem (`Tool`, `ActionTool`) e o nível de cada uma, não como cada ferramenta acha o lançamento certo ou monta a ficha.
- O assistente simulado (`FakeProvider`, `FakeIntents`, `FakeParser`): as regras com que ele entende as frases.
- O formato exato do pedido ao Gemini (`GeminiPayload`) e a leitura do streaming (`GeminiStream`) além do que está descrito.
- As contas de calendário: as repetições dos lembretes (`Recurrence`), as faturas na virada do mês e as parcelas (`InstallmentService`, `InvoiceCalendar`). Os testes deles estão em `backend/tests/Unit/` e `backend/tests/Feature/Finance/`.
- Os e-mails e as páginas de aprovação (`backend/resources/views/`), a casa de demonstração (`DemoHouseholdSeeder`) e o gerador da marca (`scripts/brand-icons.mjs`).

**O que não dá para confirmar pelo código:**

- **Produção:** os arquivos estão no repositório (`compose.prod.yml`, `docker/php/Dockerfile.prod`, `backend/.env.production.example` e o [guia de deploy](../deploy.md)), mas o comportamento no servidor de verdade não é verificável pelo código. O resto destes documentos descreve o ambiente de desenvolvimento do `docker-compose.yml` e do `.env.example`.
- **O Gemini de verdade:** os testes usam a IA e o transcritor simulados, e o Gemini é testado com respostas falsas. A qualidade das respostas, o tempo e o custo reais dependem do serviço.
- **Desempenho e carga:** não há testes de carga; os limites por minuto e por dia são os do código.
- **iOS:** o comportamento no iPhone não foi verificado nesta análise.
**O que o sistema ainda não tem** (também listado no [README](../../README.md#limitações-e-próximos-passos)): notificações push, o link do convite abrindo o app e a publicação nas lojas.

## Convenções destes documentos

- **Caminhos** são relativos à raiz do repositório: `backend/app/Services/TransactionService.php`.
- **Classe e método** aparecem como `TransactionService::create`; função do app, como `api()` ou `useTransactions()`.
- Cada parte termina com um quadro **"Para explicar"**: o que é, o problema que resolve e uma forma curta de contar isso para alguém que não conhece o código.
- Os diagramas são em [Mermaid](https://mermaid.js.org/), que o GitHub e o VS Code desenham sozinhos e que dá para editar como texto. Eles usam o tema de quem lê (claro ou escuro), para ficarem legíveis nos dois; os desenhos com as cores e as fontes do guia estão na [página de apresentação](https://leo-o-nardo.github.io/caderneta/apresentacao.html).
- O que não pôde ser confirmado no código é dito explicitamente, em vez de presumido.
