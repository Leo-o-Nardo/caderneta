# Caderneta · API

A API Laravel da Caderneta: as regras de dinheiro, a casa, o assistente, os avisos e os e-mails. A visão geral, a arquitetura e o passo a passo completo estão no [README principal](../README.md).

## Comandos do dia a dia

Todos rodam dentro do container, a partir da raiz do projeto:

```bash
docker compose exec app php artisan test                 # testes (Pest), num banco separado
docker compose exec app ./vendor/bin/pint                # estilo do código
docker compose exec app php artisan migrate:fresh --seed # banco do zero, com a casa de demonstração
```

| Comando | Para quê |
|---|---|
| `php artisan assistant:ask "gastei 30 no mercado" --user=leo@example.com` | Conversar com o assistente pelo terminal. |
| `php artisan assistant:action {ulid} confirm` | Confirmar, cancelar ou desfazer uma ação (`confirm`, `cancel`, `undo`). |
| `php artisan assistant:voice arquivo.wav --user=leo@example.com` | Mandar um áudio pelo mesmo caminho da voz do app. |
| `php artisan assistant:usage --days=7` | Uso e custo da IA nos últimos dias. |
| `php artisan users:pending` | Quem espera a liberação do cadastro (`users:approve` e `users:reject` com o e-mail). |
| `php artisan incomes:launch` | Lançar agora as receitas fixas do dia (a fila também roda sozinha, de hora em hora). |

Depois de mudar o `.env`, reinicie quem lê ele: `docker compose restart app queue reverb`.
