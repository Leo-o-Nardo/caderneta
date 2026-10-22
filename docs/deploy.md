# Deploy da Caderneta

Como colocar a API da Caderneta num servidor Linux (Ubuntu) que já tem outros sites, sem encostar neles. Neste guia, `caderneta.example.com` é o endereço do servidor: troque pelo seu.

## Como fica no servidor

```text
internet ──► nginx do servidor (80/443, HTTPS)
               │
               ├── https://caderneta.example.com/      ──► 127.0.0.1:8100  nginx interno ──► app (PHP-FPM)
               └── wss://caderneta.example.com/app/    ──► 127.0.0.1:8180  reverb (WebSocket)

Docker, projeto "caderneta" (rede própria):
  app · queue · scheduler · reverb · nginx · postgres · redis
  volumes: pgdata (banco) · redisdata (fila) · storage (arquivos do app)
```

O que garante que os outros sites não sentem nada:

- **Nada instalado fora do Docker** além do próprio Docker. O PHP, o banco e o Redis da Caderneta são containers dela.
- **Nenhuma porta nova aberta para a internet.** Os containers escutam só em `127.0.0.1`; o banco e o Redis nem isso.
- **Limite de memória e CPU** em cada serviço (cerca de 1,7 GB no total, no pior caso) e logs com tamanho máximo.
- **No nginx do servidor, só um arquivo novo**, do domínio da Caderneta. O `nginx -t` confere tudo antes do `reload`, que não derruba conexões.

## 1. Instalar o Docker

Pelos pacotes do próprio Ubuntu (o `docker-buildx` é o que constrói as imagens):

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-v2 docker-buildx
```

Para usar o Docker sem `sudo`, coloque o seu usuário no grupo `docker` e entre de novo no SSH:

```bash
sudo usermod -aG docker $USER
```

Confira:

```bash
docker --version
docker compose version
```

## 2. Baixar o código

```bash
sudo mkdir -p /opt/caderneta
sudo chown $USER: /opt/caderneta
git clone https://github.com/Leo-o-Nardo/caderneta.git /opt/caderneta
cd /opt/caderneta
```

## 3. Preencher o `.env`

```bash
cp backend/.env.production.example backend/.env
sudo chown $USER:www-data backend/.env
chmod 640 backend/.env
```

O PHP dos containers roda como `www-data`. Por isso o grupo precisa ler o arquivo; os outros usuários do servidor, não. Sem a leitura, o Laravel segue sem o `.env` e tenta o SQLite.

Gere os segredos direto no arquivo, sem copiar e colar (uma chave cortada na colagem derruba o app com "Unsupported cipher or incorrect key length"):

```bash
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" backend/.env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 24)|" backend/.env
sed -i "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=$(openssl rand -hex 16)|" backend/.env
sed -i "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(openssl rand -hex 16)|" backend/.env
```

A chave do app tem que ter 32 bytes; este comando confere e responde `32`:

```bash
grep "^APP_KEY=" backend/.env | cut -d: -f2 | base64 -d | wc -c
```

Depois, com o editor (`nano backend/.env`), preencha `APP_URL` e `INVITE_URL` com o domínio, a chave do Gemini, o e-mail de envio e o `ADMIN_EMAIL`. Evite o caractere `$` nas senhas: o Docker Compose lê este arquivo e trataria o `$` como variável.

Se mudar o `.env` com a Caderneta já no ar, reinicie os containers do PHP, que guardam a configuração em cache ao iniciar (o nginx interno acha o `app` reiniciado sozinho):

```bash
docker compose --env-file backend/.env -f compose.prod.yml restart app queue scheduler reverb
```

## 4. Subir a Caderneta

```bash
bash scripts/deploy.sh
```

Na primeira vez, o build leva alguns minutos. O script constrói as imagens, sobe o banco, roda as migrations e sobe o resto. No fim, todos os serviços devem aparecer como `running` (o banco, como `healthy`).

Confira por dentro do servidor, ainda sem HTTPS:

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8100/up
```

Tem que responder `200`.

## 5. Ligar o domínio no nginx do servidor

Primeiro, um certificado do Let's Encrypt só para o domínio da Caderneta. O certbot usa o nginx só durante a validação e não altera os arquivos dos outros sites. O `--deploy-hook` recarrega o nginx a cada renovação automática, para ele passar a usar o certificado novo:

```bash
sudo certbot certonly --nginx -d caderneta.example.com --deploy-hook "systemctl reload nginx"
```

Confira se a renovação automática vai funcionar:

```bash
sudo certbot renew --dry-run
```

O arquivo do site está em `deploy/nginx/caderneta.conf`. Copie e troque `DOMINIO` pelo endereço (isso também acerta os caminhos do certificado):

```bash
sudo cp deploy/nginx/caderneta.conf /etc/nginx/sites-available/caderneta
sudo sed -i 's/DOMINIO/caderneta.example.com/g' /etc/nginx/sites-available/caderneta
```

Ative, confira e recarregue. Se o `nginx -t` acusar erro, nada muda: corrija e rode de novo.

```bash
sudo ln -s /etc/nginx/sites-available/caderneta /etc/nginx/sites-enabled/caderneta
sudo nginx -t
sudo systemctl reload nginx
```

Confira de fora:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://caderneta.example.com/up
```

## 6. Backup diário do banco

```bash
sudo mkdir -p /var/backups/caderneta
sudo chown $USER: /var/backups/caderneta
bash scripts/backup.sh
```

Se o teste gerou o arquivo, agende para todo dia às 3h (`crontab -e` e acrescente a linha):

```text
0 3 * * * /usr/bin/bash /opt/caderneta/scripts/backup.sh >> /var/backups/caderneta/backup.log 2>&1
```

O script guarda os últimos 14 dias. Vale copiar a pasta para fora do servidor de vez em quando.

## 7. Conferir

- O outro site do servidor continua respondendo normalmente.
- `https://caderneta.example.com/` mostra a página da Caderneta.
- Um cadastro novo no app manda o e-mail de liberação para o `ADMIN_EMAIL`, e o link abre a página de liberar.
- Depois de liberado, uma mensagem de voz vira lançamento, com a resposta chegando aos pedaços (o WebSocket funcionando).
- O consumo fica dentro dos limites: `docker stats --no-stream`.

## Atualizar depois

Depois de cada push no GitHub:

```bash
cd /opt/caderneta && bash scripts/deploy.sh
```

## Comandos do dia a dia

Todos da pasta `/opt/caderneta`. Para encurtar, crie um atalho na sessão:

```bash
alias dc='docker compose --env-file backend/.env -f compose.prod.yml'
```

| Para | Comando |
| --- | --- |
| Ver os serviços | `dc ps` |
| Ver os logs (Ctrl+C sai) | `dc logs -f --tail=100 app queue` |
| Quem espera liberação | `dc exec app php artisan users:pending` |
| Liberar ou recusar | `dc exec app php artisan users:approve email@exemplo.com` (ou `users:reject`) |
| Reiniciar tudo | `dc restart` |
| Memória e CPU | `docker stats --no-stream` |

Para restaurar um backup (apaga o banco atual):

```bash
dc stop app queue scheduler reverb
gunzip -c /var/backups/caderneta/ARQUIVO.sql.gz | dc exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
dc start app queue scheduler reverb
```

## Tirar tudo

Se um dia precisar remover a Caderneta do servidor, sem afetar o resto:

```bash
cd /opt/caderneta
docker compose --env-file backend/.env -f compose.prod.yml down
sudo rm /etc/nginx/sites-enabled/caderneta
sudo nginx -t && sudo systemctl reload nginx
```

O `down` mantém os volumes (o banco continua guardado). Para apagar os dados também, acrescente `--volumes`.
