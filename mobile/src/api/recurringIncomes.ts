import { useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';

import { keys } from './keys';
import type { RecurringIncome, Visibility } from './types';

/** Os campos de uma receita fixa. Ao editar, valem para os próximos meses; os já lançados ficam como estão. */
export type RecurringIncomeInput = {
  description: string;
  amount_cents: number;
  category_id: number;
  day_of_month: number;
  visibility: Visibility;
};

/** As receitas fixas que continuam repetindo, pelo dia do mês. */
export function useRecurringIncomes() {
  return useQuery({ queryKey: keys.recurringIncomes, queryFn: () => api<RecurringIncome[]>('/recurring-incomes') });
}

export function useRecurringIncome(id: number) {
  return useQuery({ queryKey: keys.recurringIncome(id), queryFn: () => api<RecurringIncome>(`/recurring-incomes/${id}`) });
}

/** Cadastro direto (Agenda): começa no próximo dia que cair; se é hoje, a receita de hoje já entra. */
export function createRecurringIncome(input: RecurringIncomeInput): Promise<RecurringIncome> {
  return api('/recurring-incomes', { method: 'POST', body: input });
}

export function updateRecurringIncome(id: number, input: RecurringIncomeInput): Promise<RecurringIncome> {
  return api(`/recurring-incomes/${id}`, { method: 'PATCH', body: input });
}

/** "Parar de repetir": os próximos meses não são lançados; o que já foi lançado continua. */
export function stopRecurringIncome(id: number): Promise<void> {
  return api(`/recurring-incomes/${id}`, { method: 'DELETE' });
}
