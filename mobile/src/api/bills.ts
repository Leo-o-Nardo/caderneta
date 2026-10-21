import { useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';

import { keys } from './keys';
import type { Bill, BillDetail, BillMonth, BillOccurrence, PaymentType } from './types';

export type BillInput = {
  name: string;
  category_id: number;
  expected_cents: number | null;
  amount_varies: boolean;
  due_day: number;
  autopay: boolean;
};

export type PayBillInput = {
  month: string;
  amount_cents: number;
  paid_on: string;
  payment_type: PaymentType;
  card_id: number | null;
};

/** Todas as contas ativas num mês ("2026-10"), com a situação de cada uma. */
export function useBillsMonth(month: string) {
  return useQuery({
    queryKey: keys.billsMonth(month),
    queryFn: () => api<BillOccurrence[]>(`/bills/month?month=${month}`),
  });
}

/** As atrasadas (do mês passado e deste) e as que vencem nos próximos dias. */
export function useUpcomingBills(days = 30) {
  return useQuery({
    queryKey: keys.billsUpcoming(days),
    queryFn: () => api<BillOccurrence[]>(`/bills/upcoming?days=${days}`),
  });
}

/** A conta com os últimos 6 meses. */
export function useBill(id: number) {
  return useQuery({ queryKey: keys.bill(id), queryFn: () => api<BillDetail>(`/bills/${id}`) });
}

export function createBill(input: BillInput): Promise<Bill> {
  return api('/bills', { method: 'POST', body: input });
}

export function updateBill(id: number, input: BillInput): Promise<Bill> {
  return api(`/bills/${id}`, { method: 'PATCH', body: input });
}

/** Arquiva: some das listas, mas os meses pagos continuam no histórico de lançamentos. */
export function archiveBill(id: number): Promise<void> {
  return api(`/bills/${id}`, { method: 'DELETE' });
}

/** Marca o mês como pago e lança a despesa. */
export function payBill(id: number, input: PayBillInput): Promise<BillOccurrence> {
  return api(`/bills/${id}/pay`, { method: 'POST', body: input });
}

/** "Esse mês não veio". */
export function skipBillMonth(id: number, month: string): Promise<BillOccurrence> {
  return api(`/bills/${id}/skip`, { method: 'POST', body: { month } });
}

/** Desfaz o pagamento (apagando a despesa) ou o pulo de um mês. */
export function undoBillMonth(id: number, month: string): Promise<void> {
  return api(`/bills/${id}/payments/${month}`, { method: 'DELETE' });
}

export function isOpen(bill: Pick<BillMonth, 'status'>): boolean {
  return bill.status !== 'paid' && bill.status !== 'skipped';
}
