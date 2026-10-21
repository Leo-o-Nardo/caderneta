import { queryOptions, useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';

import { keys } from './keys';
import type { Summary } from './types';

/** O resumo de um mês ("2026-10"): totais, categorias, membros, faturas, orçamentos e contas. */
export function summaryQuery(month: string) {
  return queryOptions({ queryKey: keys.summary(month), queryFn: () => api<Summary>(`/summary?month=${month}`) });
}

export function useSummary(month: string) {
  return useQuery(summaryQuery(month));
}
