# Caderneta nas lojas

Como gerar os builds do app e levar para a Play Store e a App Store. Os builds rodam na nuvem da Expo (EAS), então não precisa de Android Studio nem de Mac. Neste guia, `caderneta.example.com` é o endereço do servidor: troque pelo seu.

## O que já está configurado

- **Identificador do app** (`mobile/app.json`): `com.leonardoferreira.caderneta`, no Android e no iOS. Ele não muda depois de publicado.
- **iOS:** só iPhone (`supportsTablet: false`) e a declaração de que o app não usa criptografia própria (`usesNonExemptEncryption: false`), que a Apple pede a cada envio.
- **Perfis de build** (`mobile/eas.json`):
  - `preview`: um APK para instalar direto no celular, sem loja;
  - `production`: o pacote das lojas (AAB no Android), com o número do build subindo sozinho a cada build (`autoIncrement`, guardado na Expo por `appVersionSource: remote`).
- **Endereço do servidor:** não fica no repositório. Os builds leem as variáveis `EXPO_PUBLIC_*` dos ambientes `preview` e `production` do EAS, guardadas na conta da Expo.

## 1. Conta e projeto na Expo

Crie uma conta gratuita em expo.dev. Depois, na pasta `mobile/`:

```bash
npx eas-cli@latest login
npx eas-cli@latest init
```

O `init` cria o projeto na Expo e acrescenta o `extra.eas.projectId` e o `owner` no `app.json`. Esses dois entram no Git.

## 2. Variáveis do servidor

As mesmas do `mobile/.env.example`, agora apontando para a produção. Rode da pasta `mobile/`, trocando o domínio e a chave pública do Reverb (a `REVERB_APP_KEY` do `backend/.env` do servidor):

```bash
npx eas-cli@latest env:set --name EXPO_PUBLIC_API_URL --value https://caderneta.example.com/api/v1 --environment preview --environment production --visibility plaintext
npx eas-cli@latest env:set --name EXPO_PUBLIC_REVERB_URL --value wss://caderneta.example.com --environment preview --environment production --visibility plaintext
npx eas-cli@latest env:set --name EXPO_PUBLIC_REVERB_KEY --value CHAVE_PUBLICA_DO_REVERB --environment preview --environment production --visibility plaintext
```

Para conferir o que ficou guardado: `npx eas-cli@latest env:list --environment production`.

São públicas de propósito: vão dentro do app, como qualquer endereço de API.

## 3. O APK para testar

```bash
npx eas-cli@latest build --profile preview --platform android
```

Na primeira vez, o EAS pergunta se pode gerar a chave de assinatura do Android: responda que sim (ela fica guardada na Expo). No fim, ele mostra um link e um QR code: abra no celular, baixe o APK e instale (o Android pede para permitir a instalação de fora da loja).

## 4. Notificações push

Os avisos do Chat (contas, lembretes, orçamento, resumo da semana) também saem como notificação, pelo serviço de push da Expo. No Android, quem entrega é o Firebase; no iPhone, a Apple. O push não funciona no Expo Go: os testes usam o build `development` (o app com o Metro, como no Expo Go) ou o `preview`.

### Android: o Firebase

1. Em console.firebase.google.com, crie um projeto (o Google Analytics pode ficar desligado).
2. Adicione um app Android com o pacote `com.leonardoferreira.caderneta` e baixe o `google-services.json`. Ele fica na pasta `mobile/`, mas não entra no Git (está no `.gitignore`).
3. Em Configurações do projeto › Contas de serviço, clique em "Gerar nova chave privada". O arquivo JSON baixado é a chave que deixa a Expo mandar pelo Firebase. Guarde fora do projeto.

Mande o `google-services.json` para o EAS como variável de arquivo. O `app.config.ts` lê a variável durante o build:

```bash
npx eas-cli@latest env:set --name GOOGLE_SERVICES_JSON --type file --value ./google-services.json --environment development --environment preview --environment production --visibility secret
```

E a chave da conta de serviço para a Expo:

```bash
npx eas-cli@latest credentials --platform android
```

No menu: o perfil (`development`, depois os outros) › Google Service Account › "Manage your Google Service Account Key for Push Notifications (FCM V1)" › "Set up a Google Service Account Key for Push Notifications (FCM V1)" › e o caminho do arquivo JSON baixado no passo 3.

### O build de desenvolvimento

```bash
npx eas-cli@latest build --profile development --platform android
```

Instale o APK como o do `preview`. Depois, `npx expo start` abre o projeto nesse app (e não no Expo Go): a tela inicial do app instalado mostra o servidor do Metro para tocar. Para voltar ao Expo Go, aperte `s` no terminal do Expo.

### Testar

Entre no app e aceite as notificações (na folha do Início ou em Configurações › Avisos). No servidor:

```bash
sudo docker compose --env-file backend/.env -f compose.prod.yml exec app php artisan alerts:test-push seu-email@exemplo.com
```

A notificação "Caderneta" chega com o app fechado. Tocar nela abre o Chat. Depois, um lembrete de verdade, marcado para dali a dois minutos, chega como "Lembrete".

### iPhone

A chave de push da Apple é gerada pelo próprio EAS na hora do primeiro build do iOS, com a conta Apple Developer: ele pergunta e guarda. Nada a fazer até lá.
