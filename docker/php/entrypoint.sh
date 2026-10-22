#!/bin/sh
# Antes de cada container começar (app, fila, agendador e Reverb): guarda em cache a configuração,
# as rotas, os eventos e as telas, lendo o .env montado. Cada container tem o seu cache.
set -e

php artisan optimize --quiet

exec "$@"
