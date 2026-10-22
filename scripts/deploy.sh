#!/usr/bin/env bash
# Atualiza a Caderneta no servidor: baixa o código, reconstrói as imagens, roda as migrations e
# sobe os containers novos. Rode com o usuário que é dono da pasta (e está no grupo docker):
#   bash scripts/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/.."

compose() {
  docker compose --env-file backend/.env -f compose.prod.yml "$@"
}

if [ ! -f backend/.env ]; then
  echo "Falta o backend/.env (copie de backend/.env.production.example e preencha)." >&2
  exit 1
fi

echo "› Baixando o código"
git pull --ff-only

echo "› Construindo as imagens"
compose build

echo "› Subindo o banco e o Redis"
compose up -d postgres redis

# As migrations rodam antes do código novo entrar no ar, num container que some no fim.
echo "› Rodando as migrations"
compose run --rm app php artisan migrate --force

echo "› Subindo a Caderneta"
compose up -d --remove-orphans

# Tira as imagens antigas que ficaram sem uso depois do build.
docker image prune -f > /dev/null

echo "› Pronto"
compose ps
