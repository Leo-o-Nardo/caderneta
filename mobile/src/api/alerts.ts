import { queryOptions, useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

import { replaceMessage } from './assistant';
import { invalidateFinance, keys } from './keys';
import type { ChatMessage } from './types';

/** Os botões dos cards de aviso (ver AlertController na API). */
export type AlertAction = 'done' | 'snooze_hour' | 'tomorrow' | 'remind_tomorrow' | 'paid' | 'undo';

/**
 * Um botão do card de aviso. A resposta traz a mensagem com o card atualizado ("Feito", "Paga"),
 * que entra no lugar da antiga. Lembretes, contas e lançamentos podem ter mudado.
 */
export async function alertAction(messageId: number, action: AlertAction): Promise<void> {
  const { message } = await api<{ message: ChatMessage }>(`/alerts/${messageId}/${action}`, { method: 'POST' });

  replaceMessage(message);
  invalidateFinance();
}

/** Quantos avisos chegaram desde a última vez que o Chat foi aberto: o número vermelho da aba. */
export const unreadAlertsQuery = queryOptions({
  queryKey: keys.unreadAlerts,
  queryFn: () => api<{ count: number }>('/alerts/unread'),
});

export function useUnreadAlerts() {
  return useQuery({ ...unreadAlertsQuery, select: (data) => data.count });
}

/** O Chat foi aberto: o número da aba zera na hora (e na API). */
export async function markAlertsSeen(): Promise<void> {
  queryClient.setQueryData(keys.unreadAlerts, { count: 0 });
  await api('/alerts/seen', { method: 'POST' });
}
