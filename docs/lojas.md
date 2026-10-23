# Caderneta nas lojas

Como gerar os builds do app e levar para a Play Store e a App Store. Os builds rodam na nuvem da Expo (EAS), então não precisa de Android Studio nem de Mac. Neste guia, `caderneta.example.com` é o endereço do servidor: troque pelo seu.

## O que já está configurado

- **Identificador do app** (`mobile/app.json`): `com.leonardoferreira.caderneta`, no Android e no iOS. Ele não muda depois de publicado.
- **iOS:** só iPhone (`supportsTablet: false`) e a declaração de que o app não usa criptografia própria (`usesNonExemptEncryption: false`), que a Apple pede a cada envio.
- **Perfis de build** (`mobile/eas.json`):
  - `preview`: um APK para instalar direto no celular, sem loja;
  - `production`: o pacote das lojas (AAB no Android), com o número do build subindo sozinho a cada build (`autoIncrement`, guardado na Expo por `appVersionSource: remote`).
- **Endereço do servidor:** não fica no repositório. Os builds leem as variáveis `EXPO_PUBLIC_*` dos ambientes `preview` e `production` do EAS, guardadas na conta da Expo.
- **Permissões:** só o microfone (e o ajuste de áudio que vem com ele) e as notificações. O áudio em segundo plano do `expo-audio` está desligado (`enableBackgroundPlayback: false`) e as permissões de serviço em primeiro plano estão bloqueadas (`blockedPermissions`): com elas, a Play pede uma declaração com vídeo, e a Apple pergunta pelo modo de áudio em segundo plano.

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

Ele serve para mexer no código e ver na hora. Para só testar contra o servidor, o APK do `preview` (seção 3) já recebe as notificações e não depende do Metro.

### Testar

Entre no app e aceite as notificações (na folha do Início ou em Configurações › Avisos). No servidor:

```bash
sudo docker compose --env-file backend/.env -f compose.prod.yml exec app php artisan alerts:test-push seu-email@exemplo.com
```

A notificação "Caderneta" chega com o app fechado. Tocar nela abre o Chat. Depois, um lembrete de verdade, marcado para dali a dois minutos, chega como "Lembrete".

### iPhone

A chave de push da Apple é gerada pelo próprio EAS na hora do primeiro build do iOS, com a conta Apple Developer: ele pergunta e guarda. Nada a fazer até lá.

## 5. O que as lojas pedem

### Excluir a conta

As duas lojas exigem que a conta possa ser excluída pelo app, e o Google Play pede também um endereço na web, para quem já desinstalou.

- **No app:** Configurações › Conta › Excluir conta. Pede a senha e confirma (`DELETE /me`).
- **Na web:** `https://caderneta.example.com/excluir-conta`, com e-mail e senha. É este endereço que vai no formulário "Segurança dos dados" do Play Console, em "Exclusão de dados".

Os dois caminhos fazem a mesma coisa (`AccountService`). Saem o login em todos os celulares, as conversas, os avisos, os lembretes e os cartões da pessoa. Nas casas compartilhadas, os lançamentos, as contas e as listas ficam para quem continua, sem o nome de quem lançou. Onde a pessoa era a única dona, a casa passa para o membro mais antigo que pode editar; sem ninguém assim, a casa é apagada.

### Política de privacidade

`https://caderneta.example.com/privacidade`. O endereço vai nas fichas das duas lojas; no app, fica em Configurações › Conta e na tela de criar conta. O texto descreve o que o código faz: o que é guardado, o que vai para a IA (com o cofre), o áudio, as notificações, por quanto tempo e como excluir. Ao mudar o que o app guarda ou para onde envia, mude a página (`backend/resources/views/privacy.blade.php`) e a data dela.

Duas variáveis do `.env` do servidor aparecem nela:

- `PRIVACY_CONTROLLER`: quem responde pelos dados. Precisa ser quem publica o app, o mesmo nome da conta de desenvolvedor nas lojas.
- `CONTACT_EMAIL`: o e-mail de contato. Sem ele, vale o `ADMIN_EMAIL`.

### Conta de demonstração

As duas lojas pedem um login para a revisão. No servidor:

```bash
sudo docker compose --env-file backend/.env -f compose.prod.yml exec app php artisan demo:account revisao@exemplo.com --password=UmaSenhaForte123
```

A conta já sai liberada, com a casa de exemplo de 3 meses dividida com a "Ana". Rodar de novo refaz tudo do zero, com as datas do dia: vale rodar antes de cada envio para revisão. Com a mesma senha, o que está no formulário das lojas continua valendo. O e-mail não precisa existir, porque nenhum e-mail é enviado para ele.

### Reportar uma resposta da IA

O Google Play exige que apps com IA que gera texto deixem a pessoa reportar uma resposta ofensiva. No Chat, cada resposta do assistente tem uma bandeirinha depois da hora: ela confirma e manda a resposta, com a pergunta de antes, para o `ADMIN_EMAIL`.
