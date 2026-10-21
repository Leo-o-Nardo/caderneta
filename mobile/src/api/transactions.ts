import { infiniteQueryOptions, useInfiniteQuery, useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';

import { keys } from './keys';
import { toQuery } from './query';
import type { CategoryKind, CursorPage, PaymentType, Transaction, Visibility } from './types';

export type TransactionFilters = {
  month?: string;
  from?: string;
  to?: string;
  kind?: CategoryKind;
  category_id?: number;
  q?: string;
  per_page?: number;
};

export type TransactionInput = {
  kind: CategoryKind;
  amount_cents: number;
  description: string;
  occurred_on: string;
  category_id: number;
  payment_type?: PaymentType | null;
  card_id?: number | null;
  visibility: Visibility;
  // "Repetir todo mês" (só receita): a API cria junto a receita fixa que lança os próximos meses.
  repeat_monthly?: boolean;
};

/** A lista de lançamentos, 50 por vez: cada página traz o cursor da próxima. */
export function transactionsQuery(filters: TransactionFilters) {
  return infiniteQueryOptions({
    queryKey: keys.transactions(filters),
    queryFn: ({ pageParam }) =>
      api<CursorPage<Transaction>>(`/transactions?${toQuery({ per_page: 50, ...filters, cursor: pageParam })}`),
    initialPageParam: null as string | null,
    getNextPageParam: (page) => page.meta.next_cursor,
  });
}

export function useTransactions(filters: TransactionFilters) {
  return useInfiniteQuery(transactionsQuery(filters));
}

export function useTransaction(id: number) {
  return useQuery({ queryKey: keys.transaction(id), queryFn: () => api<Transaction>(`/transactions/${id}`) });
}

export function createTransaction(input: TransactionInput): Promise<Transaction> {
  return api('/transactions', { method: 'POST', body: input });
}

/** O tipo (despesa ou receita) não muda numa edição. */
export function updateTransaction(id: number, input: Partial<Omit<TransactionInput, 'kind'>>): Promise<Transaction> {
  return api(`/transactions/${id}`, { method: 'PATCH', body: input });
}

export function deleteTransaction(id: number): Promise<void> {
  return api(`/transactions/${id}`, { method: 'DELETE' });
}
