# Caderneta · App

O app Expo (React Native) da Caderneta. A visão geral, a arquitetura e o passo a passo completo estão no [README principal](../README.md).

## Comandos do dia a dia

```bash
npm install          # dependências
npx expo start -c    # abre o app (Expo Go ou emulador), limpando o cache
npm test             # testes (Jest)
npx tsc --noEmit     # tipos
npm run lint         # ESLint
```

O `.env` sai do `.env.example`: o endereço da API e do Reverb (`10.0.2.2` é o PC visto pelo emulador Android) e a chave pública do Reverb, a mesma `REVERB_APP_KEY` do `backend/.env`.

## Onde fica cada coisa

- `src/app/`: as telas, pelas rotas do Expo Router.
- `src/components/ui/`: os componentes do guia de design (`docs/design-guide.html`).
- `src/theme/tokens.ts`: as cores, os espaços, os cantos e as fontes do guia.
- `src/theme/brand.ts`: o desenho do ícone, gerado por `scripts/brand-icons.mjs` (não edite à mão).
