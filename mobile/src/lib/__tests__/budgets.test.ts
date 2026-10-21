import type { BudgetUsage } from '@/api/types';

import {
  averageLabel,
  budgetStatus,
  budgetStatusLabel,
  budgetTotals,
  overBudgetLabel,
  remainingInBudgets,
  sortByAttention,
  suggestedLimit,
} from '../budgets';

describe('suggestedLimit', () => {
  it('rounds the average up to the next R$ 10', () => {
    expect(suggestedLimit(63452)).toBe(64000);
    expect(suggestedLimit(64000)).toBe(64000);
    expect(suggestedLimit(1)).toBe(1000);
  });
});

describe('averageLabel', () => {
  it('uses the singular for a single month', () => {
    expect(averageLabel(1)).toBe('Média do último mês');
    expect(averageLabel(3)).toBe('Média dos últimos 3 meses');
  });
});

describe('budgetTotals', () => {
  const budget = (limit: number, spent: number): BudgetUsage => ({
    category: { id: limit, name: 'Mercado', icon: 'cart', color: '#4F7F4F' },
    limit_cents: limit,
    spent_cents: spent,
    remaining_cents: limit - spent,
    percent: Math.floor((spent * 100) / limit),
    status: 'ok',
  });

  it('adds every budget of the month', () => {
    expect(budgetTotals([budget(80000, 64000), budget(20000, 30000)])).toEqual({
      limitCents: 100000,
      spentCents: 94000,
      percent: 94,
      status: 'warning',
    });
  });

  it('is zero without budgets', () => {
    expect(budgetTotals([])).toEqual({ limitCents: 0, spentCents: 0, percent: 0, status: 'ok' });
  });
});

describe('budgetStatus', () => {
  it('warns from 80% and is over from 100%', () => {
    expect(budgetStatus(79)).toBe('ok');
    expect(budgetStatus(80)).toBe('warning');
    expect(budgetStatus(99)).toBe('warning');
    expect(budgetStatus(100)).toBe('over');
  });
});

describe('budgetStatusLabel', () => {
  it('puts the money first and the percent after', () => {
    expect(budgetStatusLabel({ remaining_cents: 11570, percent: 85 })).toBe('Restam R$ 115,70 · 85% usado');
    expect(budgetStatusLabel({ remaining_cents: 0, percent: 100 })).toBe('Chegou ao limite · 100%');
    expect(budgetStatusLabel({ remaining_cents: -21280, percent: 170 })).toBe('Passou R$ 212,80 do limite · 170%');
  });
});

describe('budgets of the month', () => {
  const usage = (name: string, limit: number, spent: number): BudgetUsage => {
    const percent = Math.floor((spent * 100) / limit);

    return {
      category: { id: limit, name, icon: 'cart', color: '#4F7F4F' },
      limit_cents: limit,
      spent_cents: spent,
      remaining_cents: limit - spent,
      percent,
      status: budgetStatus(percent),
    };
  };

  const delivery = usage('Delivery', 30000, 51280);
  const market = usage('Mercado', 80000, 68430);
  const transport = usage('Transporte', 25000, 18620);
  const leisure = usage('Lazer', 40000, 14200);

  it('sorts by what needs attention: over, near the limit, then the fullest', () => {
    expect(sortByAttention([leisure, market, transport, delivery]).map((budget) => budget.category.name)).toEqual([
      'Delivery',
      'Mercado',
      'Transporte',
      'Lazer',
    ]);
  });

  it('adds what is left only in the budgets that are not over', () => {
    expect(remainingInBudgets([delivery, market, transport, leisure])).toBe(43750);
  });

  it('names the budgets that are over', () => {
    expect(overBudgetLabel([market, transport])).toBeNull();
    expect(overBudgetLabel([delivery, market])).toBe('Delivery estourou');
    expect(overBudgetLabel([delivery, usage('Lazer', 100, 200)])).toBe('Delivery e Lazer estouraram');
    expect(overBudgetLabel([delivery, usage('Lazer', 100, 200), usage('Pets', 100, 300)])).toBe('3 categorias estouraram');
  });
});
