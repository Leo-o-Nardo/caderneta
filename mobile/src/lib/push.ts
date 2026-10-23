import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { colors } from '@/theme/tokens';

import { api } from './api';
import { Notifications } from './notifications';

// Se a folha "Receber avisos no celular?" do Início já apareceu neste aparelho.
const ASKED_KEY = 'push_asked';

// O canal do Android para os avisos (o mesmo channelId que o backend manda, ver ExpoPush).
const CHANNEL = 'avisos';

// Com o app aberto, o aviso já chega no Chat pelo canal ao vivo, com o número na aba: a notificação
// não aparece por cima.
Notifications?.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: false,
    shouldShowList: false,
    shouldPlaySound: false,
    shouldSetBadge: false,
  }),
});

/**
 * granted; undetermined (ainda dá para pedir); denied (bloqueadas: só nas configurações do celular);
 * ou unavailable (emulador ou Expo Go, sem push).
 */
export type PushStatus = 'granted' | 'undetermined' | 'denied' | 'unavailable';

export async function pushStatus(): Promise<PushStatus> {
  if (!Notifications || !Device.isDevice) {
    return 'unavailable';
  }

  const permission = await Notifications.getPermissionsAsync();

  if (permission.granted) {
    return 'granted';
  }

  // Alguns Android dizem "negado" antes de perguntar a primeira vez: o que vale é se o sistema
  // ainda mostra o pedido (canAskAgain).
  return permission.canAskAgain ? 'undetermined' : 'denied';
}

/** Se a folha do Início já apareceu (e a pessoa respondeu) neste aparelho. */
export async function wasAsked(): Promise<boolean> {
  return (await SecureStore.getItemAsync(ASKED_KEY)) !== null;
}

export function markAsked(): Promise<void> {
  return SecureStore.setItemAsync(ASKED_KEY, '1');
}

/**
 * Pede a permissão do sistema e, se a pessoa aceitar, registra o aparelho. No iPhone, o pedido do
 * sistema só aparece uma vez na vida do app: por isso a folha do app explica antes de chamar aqui.
 */
export async function enablePush(): Promise<boolean> {
  await markAsked();

  if (!Notifications) {
    return false;
  }

  // No Android 13+, o pedido de permissão só aparece se já existir um canal.
  await ensureChannel();

  if (!(await Notifications.requestPermissionsAsync()).granted) {
    return false;
  }

  await registerDevice();

  return true;
}

/**
 * Manda o endereço deste aparelho para a API. Roda a cada abertura com a permissão dada: o endereço
 * pode mudar, e a API troca sozinha se outra conta tinha entrado neste celular.
 */
export async function registerDevice(): Promise<void> {
  if (!Notifications || !Device.isDevice) {
    return;
  }

  await ensureChannel();

  const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
  const { data: token } = await Notifications.getExpoPushTokenAsync({ projectId });

  await api('/push-tokens', { method: 'POST', body: { token, platform: Platform.OS } });
}

/** O canal "Avisos" do Android, com prioridade alta (aparece no alto da tela) e a cor Musgo. */
async function ensureChannel(): Promise<void> {
  if (!Notifications || Platform.OS !== 'android') {
    return;
  }

  await Notifications.setNotificationChannelAsync(CHANNEL, {
    name: 'Avisos',
    description: 'Contas vencendo, lembretes, orçamentos e o resumo da semana.',
    importance: Notifications.AndroidImportance.HIGH,
    lightColor: colors.accent,
  });
}
