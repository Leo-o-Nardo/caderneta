import type {
  BillCreatedCard,
  BudgetSetCard,
  CardAction,
  IncomeCreatedCard,
  ListCreatedCard,
  ListUpdateCard,
} from '@/api/types';

import {
  doneCardText,
  firstItemWithError,
  isExpired,
  itemErrors,
  itemsCountLabel,
  transactionsPayload,
  undoSecondsLeft,
  type TransactionDraft,
} from '../actions';

const draft = (overrides: Partial<TransactionDraft> = {}): TransactionDraft => ({
  kind: 'expense',
  amount_cents: 1200,
  description: 'Café',
  occurred_on: '2026-10-09',
  category_id: 2,
  payment_type: 'credit_card',
  card_id: 7,
  installments: null,
  ...overrides,
});

describe('transactionsPayload', () => {
  it('sends the card only on credit and the installments only when split on credit', () => {
    const { items } = transactionsPayload([
      draft(),
      draft({ payment_type: 'pix', card_id: 7 }),
      draft({ installments: 10, amount_cents: 250000 }),
      draft({ kind: 'income', payment_type: 'pix' }),
    ]);

    expect(items[0]).toMatchObject({ payment_type: 'credit_card', card_id: 7 });
    expect(items[0]).not.toHaveProperty('installments');
    expect(items[1]).toMatchObject({ payment_type: 'pix', card_id: null });
    expect(items[2]).toMatchObject({ installments: 10 });
    // Receita não tem forma de pagamento.
    expect(items[3]).toMatchObject({ payment_type: null, card_id: null });
  });
});

describe('item errors', () => {
  const errors = {
    'items.1.card_id': ['Escolha o cartão usado na compra.'],
    'items.3.amount_cents': ['O campo valor é obrigatório.'],
    action: ['Essa confirmação expirou.'],
  };

  it('picks the errors of one item, by field', () => {
    expect(itemErrors(errors, 1)).toEqual({ card_id: 'Escolha o cartão usado na compra.' });
    expect(itemErrors(errors, 0)).toEqual({});
  });

  it('finds the first item to open', () => {
    expect(firstItemWithError(errors)).toBe(1);
    expect(firstItemWithError({ action: ['x'] })).toBeNull();
  });
});

describe('deadlines', () => {
  const now = Date.parse('2026-10-09T15:00:00Z');
  const action = (overrides: Partial<CardAction>): CardAction => ({
    id: '01j',
    tool: 'x',
    tier: 'confirm',
    status: 'pending',
    expires_at: null,
    undo_until: null,
    ...overrides,
  });

  it('shows a pending card as expired after the deadline', () => {
    expect(isExpired(action({ expires_at: '2026-10-09T14:59:00Z' }), now)).toBe(true);
    expect(isExpired(action({ expires_at: '2026-10-09T15:20:00Z' }), now)).toBe(false);
    expect(isExpired(action({ status: 'executed', expires_at: '2026-10-09T14:59:00Z' }), now)).toBe(false);
  });

  it('counts the seconds left to undo, only on immediate actions', () => {
    const done = action({ tier: 'immediate', status: 'executed', undo_until: '2026-10-09T15:01:30Z' });

    expect(undoSecondsLeft(done, now)).toBe(90);
    expect(undoSecondsLeft(done, Date.parse('2026-10-09T15:02:00Z'))).toBe(0);
    expect(undoSecondsLeft(action({ status: 'executed', undo_until: '2026-10-09T15:01:30Z' }), now)).toBe(0);
  });
});

describe('doneCardText', () => {
  it('tells what changed in the list', () => {
    const card: ListUpdateCard = {
      type: 'list_update',
      action: { id: '01j', tool: 'add_list_items', tier: 'immediate', status: 'executed', expires_at: null, undo_until: null },
      list: { id: 1, name: 'Mercado', kind: 'shopping' },
      added: ['Leite', 'Pão'],
      already: ['Café'],
      checked: [],
      not_found: [],
    };

    expect(doneCardText(card)).toEqual({ title: '2 itens na lista Mercado', lines: ['Leite · Pão', 'Já estava: Café'] });
  });

  const done: CardAction = { id: '01k', tool: 'x', tier: 'immediate', status: 'executed', expires_at: null, undo_until: null };
  const category = { id: 6, name: 'Contas da casa', icon: 'bolt', color: '#7F8A3A' };

  it('tells the fixed bill that was registered, with or without a fixed amount', () => {
    const bill: BillCreatedCard['bill'] = {
      id: 3,
      name: 'Internet',
      expected_cents: 9990,
      amount_varies: false,
      due_day: 10,
      autopay: true,
      category,
      first_due_on: '2026-10-10',
    };

    expect(doneCardText({ type: 'bill_created', action: done, bill })).toEqual({
      title: 'Conta fixa: Internet',
      lines: ['R$ 99,90 · vence todo dia 10 · débito automático'],
    });
    expect(
      doneCardText({ type: 'bill_created', action: done, bill: { ...bill, expected_cents: null, amount_varies: true, autopay: false } })
        .lines,
    ).toEqual(['Valor variável · vence todo dia 10']);
  });

  it('tells when the fixed income starts', () => {
    const card: IncomeCreatedCard = {
      type: 'income_created',
      action: done,
      income: {
        id: 1,
        description: 'Salário',
        amount_cents: 500000,
        day_of_month: 5,
        first_on: '2026-11-05',
        category: null,
        visibility: 'household',
      },
      launched_today: false,
    };

    expect(doneCardText(card)).toEqual({ title: 'Receita fixa: Salário', lines: ['R$ 5.000,00 todo dia 5', 'A primeira cai em 05/11'] });
    expect(doneCardText({ ...card, launched_today: true }).lines[1]).toBe('A de hoje já entrou');
  });

  it('tells the new budget next to the old one and what was spent', () => {
    const card: BudgetSetCard = {
      type: 'budget_set',
      action: done,
      category: { ...category, name: 'Mercado' },
      limit_cents: 80000,
      previous_cents: 60000,
      spent_cents: 43200,
    };

    expect(doneCardText(card)).toEqual({
      title: 'Orçamento de Mercado: R$ 800,00 por mês',
      lines: ['Antes: R$ 600,00 · gasto este mês: R$ 432,00'],
    });
    expect(doneCardText({ ...card, previous_cents: null }).lines[0]).toBe('Antes: sem orçamento · gasto este mês: R$ 432,00');
  });

  it('tells the new list and its items', () => {
    const card: ListCreatedCard = {
      type: 'list_created',
      action: done,
      list: { id: 9, name: 'Churrasco', kind: 'shopping' },
      items: ['Carne', 'Carvão'],
    };

    expect(doneCardText(card)).toEqual({ title: 'Lista Churrasco criada', lines: ['Carne · Carvão'] });
    expect(doneCardText({ ...card, items: [] }).lines).toEqual([]);
  });
});

describe('itemsCountLabel', () => {
  it('names the items by kind and falls back to "lançamentos" when they mix', () => {
    expect(itemsCountLabel(['expense'])).toBe('1 gasto');
    expect(itemsCountLabel(['expense', 'expense'])).toBe('2 gastos');
    expect(itemsCountLabel(['income'])).toBe('1 receita');
    expect(itemsCountLabel(['income', 'expense', 'expense'])).toBe('3 lançamentos');
  });
});
