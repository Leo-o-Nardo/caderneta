import { api } from '@/lib/api';

import type { InstallmentPlan, Visibility } from './types';

export type InstallmentPlanInput = {
  description: string;
  category_id: number;
  card_id: number;
  installments: number;
  total_cents: number;
  purchased_on: string;
  visibility: Visibility;
};

/** A compra parcelada: a API cria uma transação por parcela, cada uma na sua fatura. */
export function createInstallmentPlan(input: InstallmentPlanInput): Promise<InstallmentPlan> {
  return api('/installment-plans', { method: 'POST', body: input });
}

/** Descrição, categoria e visibilidade mudam em todas as parcelas de uma vez. */
export function updateInstallmentPlan(
  id: number,
  input: Pick<InstallmentPlanInput, 'description' | 'category_id' | 'visibility'>,
): Promise<InstallmentPlan> {
  return api(`/installment-plans/${id}`, { method: 'PATCH', body: input });
}

/** Apaga o parcelamento com todas as parcelas. */
export function deleteInstallmentPlan(id: number): Promise<void> {
  return api(`/installment-plans/${id}`, { method: 'DELETE' });
}
