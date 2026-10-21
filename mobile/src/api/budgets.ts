import { useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';

import { keys } from './keys';
import { toQuery } from './query';
import type { BudgetDetail, BudgetUsage } from './types';

/** Os orçamentos da casa com o gasto do mês. Categoria sem limite não aparece aqui. */
export function useBudgets(month: string) {
  return useQuery({
    queryKey: keys.budgets(month),
    queryFn: () => api<BudgetUsage[]>(`/budgets?${toQuery({ month })}`),
  });
}

/** Uma categoria de despesa: o limite (se tiver), o gasto do mês e a média dos anteriores. */
export function useBudget(categoryId: number) {
  return useQuery({
    queryKey: keys.budget(categoryId),
    queryFn: () => api<BudgetDetail>(`/budgets/${categoryId}`),
  });
}

/** Cria ou ajusta o limite: cada categoria tem no máximo um. */
export function setBudget(categoryId: number, limitCents: number): Promise<BudgetUsage> {
  return api(`/budgets/${categoryId}`, { method: 'PUT', body: { limit_cents: limitCents } });
}

export function removeBudget(categoryId: number): Promise<void> {
  return api(`/budgets/${categoryId}`, { method: 'DELETE' });
}
