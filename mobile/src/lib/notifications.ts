import Constants, { ExecutionEnvironment } from 'expo-constants';

type NotificationsModule = typeof import('expo-notifications');

/**
 * O expo-notifications, ou null no Expo Go. O Expo Go não traz a parte nativa das notificações, e
 * só importar o pacote já derruba o app ("Cannot find native module"). Por isso ele é carregado
 * aqui, e só nos builds do EAS (o de desenvolvimento também). No Expo Go, o push fica indisponível.
 */
export const Notifications: NotificationsModule | null =
  Constants.executionEnvironment === ExecutionEnvironment.StoreClient
    ? null
    : // eslint-disable-next-line @typescript-eslint/no-require-imports
      (require('expo-notifications') as NotificationsModule);
