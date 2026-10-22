#!/usr/bin/env bash
# Atualiza a Caderneta no servidor: baixa o código, reconstrói as imagens, roda as migrations e
# sobe os containers novos. Da pasta do projeto: sudo bash scripts/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/.."

compose() {
  docker compose --env-file backend/.env -f compose.prod.yml "$@"
}

if [ ! -f backend/.env ]; then
  echo "Falta o backend/.env (copie de backend/.env.production.example e preencha)." >&2
  exit 1
fi

# O PHP dos containers roda como www-data e precisa ler o .env. Sem isso, o Laravel segue sem ele
# e cai no SQLite. Editores e o "sed -i" costumam devolver o arquivo ao grupo do usuário.
if [ "$(stat -c '%G %a' backend/.env)" != "www-data 640" ]; then
  echo "O backend/.env precisa ser do grupo www-data, com permissão 640. Rode:" >&2
  echo "  sudo chown \$USER:www-data backend/.env && sudo chmod 640 backend/.env" >&2
  exit 1
fi

echo "› Baixando o código"
# Com sudo, quem roda o git é o root numa pasta de outro usuário; o safe.directory libera só esta.
git -c safe.directory="$PWD" pull --ff-only

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
