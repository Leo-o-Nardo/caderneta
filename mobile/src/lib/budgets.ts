import type { BudgetStatus, BudgetUsage } from '@/api/types';
import { colors } from '@/theme/tokens';

import { formatCents } from './money';

/** A cor da barra: Musgo dentro do limite, Atenção a partir de 80% e Tijolo estourado. */
export const BUDGET_COLORS: Record<BudgetStatus, string> = {
  ok: colors.accent,
  warning: colors.warn,
  over: colors.danger,
};

/**
 * O limite sugerido a partir da média, arredondado para cima em R$ 10: média de R$ 634,52 vira
 * R$ 640,00. Um número redondo é mais fácil de lembrar e dá uma pequena folga.
 */
export function suggestedLimit(averageCents: number): number {
  return Math.ceil(averageCents / 1000) * 1000;
}

/** "Média do último mês", "Média dos últimos 3 meses". */
export function averageLabel(months: number): string {
  return months === 1 ? 'Média do último mês' : `Média dos últimos ${months} meses`;
}

type Totals = {
  limitCents: number;
  spentCents: number;
  percent: number;
  status: BudgetStatus;
};

/** A soma de todos os orçamentos do mês, para a barra geral da tela de orçamentos. */
export function budgetTotals(budgets: BudgetUsage[]): Totals {
  const limitCents = budgets.reduce((total, budget) => total + budget.limit_cents, 0);
  const spentCents = budgets.reduce((total, budget) => total + budget.spent_cents, 0);
  const percent = limitCents > 0 ? Math.floor((spentCents * 100) / limitCents) : 0;

  return { limitCents, spentCents, percent, status: budgetStatus(percent) };
}

/** Os mesmos degraus do BudgetStatus do backend: aviso a partir de 80%, estourado a partir de 100%. */
export function budgetStatus(percent: number): BudgetStatus {
  if (percent >= 100) {
    return 'over';
  }

  return percent >= 80 ? 'warning' : 'ok';
}

/**
 * A frase embaixo da barra, pelo guia: o dinheiro primeiro, a porcentagem depois.
 * "Restam R$ 115,70 · 85% usado", "Chegou ao limite · 100%", "Passou R$ 212,80 do limite · 170%".
 */
export function budgetStatusLabel(budget: Pick<BudgetUsage, 'remaining_cents' | 'percent'>): string {
  if (budget.remaining_cents < 0) {
    return `Passou ${formatCents(-budget.remaining_cents)} do limite · ${budget.percent}%`;
  }

  if (budget.remaining_cents === 0) {
    return `Chegou ao limite · ${budget.percent}%`;
  }

  return `Restam ${formatCents(budget.remaining_cents)} · ${budget.percent}% usado`;
}

const ATTENTION: Record<BudgetStatus, number> = { over: 0, warning: 1, ok: 2 };

/** O que precisa de atenção primeiro: estourados, perto do limite e o resto, cada grupo do mais cheio. */
export function sortByAttention<T extends Pick<BudgetUsage, 'status' | 'percent'>>(budgets: T[]): T[] {
  return [...budgets].sort((a, b) => ATTENTION[a.status] - ATTENTION[b.status] || b.percent - a.percent);
}

/** "Resta nos orçamentos": o que falta nas categorias que ainda não estouraram (as estouradas não descontam). */
export function remainingInBudgets(budgets: Pick<BudgetUsage, 'remaining_cents'>[]): number {
  return budgets.reduce((total, budget) => total + Math.max(budget.remaining_cents, 0), 0);
}

/**
 * A legenda que diz quais categorias ficaram de fora do "Resta nos orçamentos": "Delivery
 * estourou", "Delivery e Lazer estouraram", "3 categorias estouraram". Null quando nenhuma estourou.
 */
export function overBudgetLabel(budgets: Pick<BudgetUsage, 'status' | 'category'>[]): string | null {
  const names = budgets.filter((budget) => budget.status === 'over').map((budget) => budget.category.name);

  if (names.length === 0) {
    return null;
  }

  if (names.length === 1) {
    return `${names[0]} estourou`;
  }

  return names.length === 2 ? `${names[0]} e ${names[1]} estouraram` : `${names.length} categorias estouraram`;
}
