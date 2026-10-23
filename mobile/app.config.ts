import type { ConfigContext, ExpoConfig } from 'expo/config';

/**
 * Completa o app.json com o que não pode ficar no repositório. O google-services.json (o projeto
 * do Firebase, que entrega as notificações no Android) vem do EAS: a variável GOOGLE_SERVICES_JSON,
 * do tipo arquivo, aponta para ele durante o build. Fora do EAS (Expo Go, Metro), não é usado.
 */
export default ({ config }: ConfigContext): ExpoConfig => ({
  ...(config as ExpoConfig),
  android: { ...config.android, googleServicesFile: process.env.GOOGLE_SERVICES_JSON },
});
