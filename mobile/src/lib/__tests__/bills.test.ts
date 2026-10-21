import { withAlpha } from '@/theme/tokens';

import { billAmountLabel, billDueLabel, dueNoticeCaption, dueNoticeTitle } from '../bills';

describe('billDueLabel', () => {
  const bill = (status: Parameters<typeof billDueLabel>[0]['status'], days: number) =>
    billDueLabel({ status, days_until_due: days, due_on: '2026-10-20' });

  it('describes the open bills by how close they are', () => {
    expect(bill('due_today', 0)).toEqual({ text: 'vence hoje', tone: 'warn' });
    expect(bill('upcoming', 1)).toEqual({ text: 'vence amanhã', tone: 'warn' });
    expect(bill('upcoming', 5)).toEqual({ text: 'vence em 5 dias', tone: 'muted' });
    expect(bill('upcoming', 13)).toEqual({ text: 'vence 20/10', tone: 'muted' });
  });

  it('describes overdue, paid and skipped bills', () => {
    expect(bill('overdue', -1)).toEqual({ text: 'venceu ontem', tone: 'danger' });
    expect(bill('overdue', -30)).toEqual({ text: 'atrasada há 30 dias', tone: 'danger' });
    expect(bill('paid', -2)).toEqual({ text: 'paga', tone: 'accent' });
    expect(bill('skipped', 3)).toEqual({ text: 'pulada', tone: 'faint' });
  });
});

describe('billAmountLabel', () => {
  it('marks the estimates with a tilde', () => {
    expect(billAmountLabel({ amount_cents: 62000, is_estimate: false })).toBe('R$ 620,00');
    expect(billAmountLabel({ amount_cents: 17900, is_estimate: true })).toBe('~R$ 179,00');
    expect(billAmountLabel({ amount_cents: null, is_estimate: true })).toBe('valor variável');
  });
});

describe('withAlpha', () => {
  it('turns a hex color into rgba', () => {
    expect(withAlpha('#3DD68C', 0.16)).toBe('rgba(61, 214, 140, 0.16)');
  });
});

describe('due notice', () => {
  const due = (name: string, status: 'overdue' | 'due_today', amount: number | null, estimate = false) => ({
    bill: { name },
    status,
    days_until_due: status === 'overdue' ? -9 : 0,
    due_on: '2026-10-10',
    amount_cents: amount,
    is_estimate: estimate,
  });

  it('names one or two bills and counts the rest', () => {
    expect(dueNoticeTitle([due('Condomínio', 'overdue', 62000)])).toBe('Condomínio atrasada há 9 dias');
    expect(dueNoticeTitle([due('Luz', 'overdue', 18900, true), due('Condomínio', 'overdue', 62000)])).toBe(
      'Luz e Condomínio estão atrasadas',
    );
    expect(dueNoticeTitle([due('Luz', 'due_today', 18900), due('Internet', 'due_today', 9990)])).toBe('Luz e Internet vencem hoje');
    expect(dueNoticeTitle([due('Luz', 'due_today', 1), due('Água', 'overdue', 1), due('Gás', 'overdue', 1)])).toBe(
      '3 contas vencendo hoje ou atrasadas',
    );
  });

  it('names a bill late in two months only once', () => {
    expect(dueNoticeTitle([due('Condomínio', 'overdue', 62000), due('Condomínio', 'overdue', 62000)])).toBe(
      'Condomínio: 2 meses em aberto',
    );
    expect(
      dueNoticeTitle([due('Luz', 'overdue', 18900), due('Condomínio', 'overdue', 62000), due('Condomínio', 'overdue', 62000)]),
    ).toBe('Luz e Condomínio estão atrasadas');
  });

  it('adds the amounts and says "cerca de" when any is an estimate', () => {
    expect(dueNoticeCaption([due('Condomínio', 'overdue', 62000)])).toBe('R$ 620,00 · ver na Agenda');
    expect(dueNoticeCaption([due('Luz', 'overdue', 18900, true), due('Condomínio', 'overdue', 62000)])).toBe(
      'Cerca de R$ 809,00 · ver na Agenda',
    );
    expect(dueNoticeCaption([due('Luz', 'overdue', null)])).toBe('Ver na Agenda');
  });
});
