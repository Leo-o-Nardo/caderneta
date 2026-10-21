import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';

import { keys } from './keys';
import { toQuery } from './query';
import type { Card, Invoice } from './types';

export type CardInput = {
  name: string;
  closing_day: number;
  due_day: number;
  limit_cents: number | null;
  color: string;
  is_default?: boolean;
};

/** Os cartões ativos de todos da casa, cada um com a fatura em aberto e o limite em uso. */
export function useCards() {
  return useQuery({ queryKey: keys.cards, queryFn: () => api<Card[]>('/cards') });
}

/** A fatura de um mês ("2026-11"); sem mês, a que está em aberto hoje. */
export function useCardInvoice(cardId: number, month?: string) {
  return useQuery({
    queryKey: keys.invoice(cardId, month ?? 'current'),
    queryFn: () => api<Invoice>(`/cards/${cardId}/invoice?${toQuery({ month })}`),
    // Ao trocar de mês, a fatura anterior continua na tela até a nova chegar (sem piscar o carregando).
    placeholderData: keepPreviousData,
  });
}

export function createCard(input: CardInput): Promise<Card> {
  return api('/cards', { method: 'POST', body: input });
}

/** Só o dono do cartão pode alterar. */
export function updateCard(id: number, input: CardInput): Promise<Card> {
  return api(`/cards/${id}`, { method: 'PATCH', body: input });
}

/** Arquiva: some das escolhas, mas as compras antigas continuam nas faturas. */
export function archiveCard(id: number): Promise<void> {
  return api(`/cards/${id}`, { method: 'DELETE' });
}
