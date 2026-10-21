import { queryClient } from '@/lib/queryClient';

/**
 * As chaves do cache do TanStack Query. Cada tela que usa um dado assina a chave dele; invalidar
 * a chave faz todas essas telas buscarem de novo.
 */
export const keys = {
  categories: (kind?: string) => ['categories', kind ?? 'all'] as const,
  cards: ['cards'] as const,
  invoice: (cardId: number, month: string) => ['cards', 'invoice', cardId, month] as const,
  transactions: (filters: object) => ['transactions', filters] as const,
  transaction: (id: number) => ['transaction', id] as const,
  installmentPlan: (id: number) => ['installment-plan', id] as const,
  summary: (month: string) => ['summary', month] as const,
  budgets: (month: string) => ['budgets', 'month', month] as const,
  budget: (categoryId: number) => ['budgets', 'detail', categoryId] as const,
  billsMonth: (month: string) => ['bills', 'month', month] as const,
  billsUpcoming: (days: number) => ['bills', 'upcoming', days] as const,
  bill: (id: number) => ['bills', 'detail', id] as const,
  recurringIncomes: ['recurring-incomes', 'all'] as const,
  recurringIncome: (id: number) => ['recurring-incomes', 'detail', id] as const,
  reminders: (status: string) => ['reminders', status] as const,
  conversation: ['conversation'] as const,
  unreadAlerts: ['alerts', 'unread'] as const,
  lists: ['lists', 'all'] as const,
  listItems: (listId: number) => ['lists', 'items', listId] as const,
  households: ['households'] as const,
  currentHousehold: ['households', 'current'] as const,
  invitations: ['households', 'invitations'] as const,
  invitation: (code: string) => ['households', 'invitation', code] as const,
};

/**
 * Depois de lançar, editar ou apagar: listas, resumo, orçamentos, faturas, contas, receitas fixas e
 * lembretes podem ter mudado. Invalidar pelo prefixo ("transactions") pega todas as variações de
 * filtro de uma vez.
 */
export function invalidateFinance(): Promise<void[]> {
  return Promise.all(
    [
      'transactions', 'transaction', 'installment-plan', 'summary', 'budgets', 'cards', 'bills', 'recurring-incomes',
      'reminders',
    ].map((prefix) => queryClient.invalidateQueries({ queryKey: [prefix] })),
  );
}
