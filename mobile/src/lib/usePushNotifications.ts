import * as Notifications from 'expo-notifications';
import { router } from 'expo-router';
import { useEffect } from 'react';

import { resetAfterHouseholdChange, switchHousehold } from '@/api/households';
import type { User } from '@/api/types';

import { pushStatus, registerDevice } from './push';

// A última notificação já tratada. Fica fora do componente: depois de sair e entrar de novo na
// conta, a mesma notificação não abre o Chat outra vez.
let handledId: string | null = null;

/**
 * O push com a pessoa logada: registra o aparelho a cada entrada ou abertura (se a permissão já foi
 * dada) e, ao tocar numa notificação, abre o Chat da casa do aviso, trocando de casa se precisar.
 * Vale também para o toque que abriu o app fechado.
 */
export function usePushNotifications(user: User | undefined): void {
  const userId = user?.id;
  const currentHouseholdId = user?.current_household?.id;

  useEffect(() => {
    if (userId === undefined) {
      return;
    }

    pushStatus()
      .then((status) => (status === 'granted' ? registerDevice() : undefined))
      .catch((error) => {
        if (__DEV__) {
          console.warn('[push] não deu para registrar o aparelho:', error);
        }
      });
  }, [userId]);

  const response = Notifications.useLastNotificationResponse();

  useEffect(() => {
    const id = response?.notification.request.identifier;

    if (!id || userId === undefined || id === handledId) {
      return;
    }

    handledId = id;
    const householdId = response.notification.request.content.data?.household_id;
    openAlert(typeof householdId === 'number' ? householdId : null, currentHouseholdId);
  }, [response, userId, currentHouseholdId]);
}

async function openAlert(householdId: number | null, currentHouseholdId: number | undefined): Promise<void> {
  if (householdId !== null && householdId !== currentHouseholdId) {
    try {
      await switchHousehold(householdId);
      await resetAfterHouseholdChange();
    } catch {
      // Saiu da casa ou ficou sem internet: abre o Chat da casa atual mesmo.
    }
  }

  router.navigate('/chat');
}
