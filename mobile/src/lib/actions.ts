import { format, parseISO } from 'date-fns';

import type {
  BillConfirmCard,
  CardAction,
  CategoryKind,
  DoneActionCard,
  ListUpdateCard,
  PaymentType,
  TransactionsConfirmCard,
} from '@/api/types';

import { shortDate } from './dates';
import { formatCents } from './money';
import { recurrenceLabel } from './reminders';

/*
 * As regras dos cards de ação do chat, sem tela: o rascunho das edições, o que vai para a API ao
 * confirmar, os erros de cada item e os prazos (expirou, ainda dá para desfazer).
 */

/** Um lançamento do card como a pessoa está editando, nos campos que a API recebe. */
export type TransactionDraft = {
  kind: CategoryKind;
  amount_cents: number;
  description: string;
  occurred_on: string;
  category_id: number | null;
  payment_type: PaymentType | null;
  card_id: number | null;
  installments: number | null;
};

export type BillDraft = {
  amount_cents: number | null;
  paid_on: string;
  payment_type: PaymentType;
  card_id: number | null;
};

/** O rascunho começa igual ao que o assistente propôs. */
export function transactionDrafts(card: TransactionsConfirmCard): TransactionDraft[] {
  return card.items.map((item) => ({
    kind: item.kind,
    amount_cents: item.amount_cents,
    description: item.description,
    occurred_on: item.occurred_on,
    category_id: item.category?.id ?? null,
    payment_type: item.payment_type,
    card_id: item.card?.id ?? null,
    installments: item.installments,
  }));
}

/**
 * O que vai no confirmar. Receita não tem forma de pagamento; o cartão só vai no crédito; e as
 * parcelas só quando é parcelado (e no crédito).
 */
export function transactionsPayload(drafts: TransactionDraft[]): { items: Record<string, unknown>[] } {
  return {
    items: drafts.map((draft) => {
      const credit = draft.kind === 'expense' && draft.payment_type === 'credit_card';

      return {
        kind: draft.kind,
        amount_cents: draft.amount_cents,
        description: draft.description,
        occurred_on: draft.occurred_on,
        category_id: draft.category_id,
        payment_type: draft.kind === 'expense' ? draft.payment_type : null,
        card_id: credit ? draft.card_id : null,
        ...(credit && draft.installments && draft.installments > 1 ? { installments: draft.installments } : {}),
      };
    }),
  };
}

export function billDraft(card: BillConfirmCard): BillDraft {
  return {
    amount_cents: card.amount_cents,
    paid_on: card.paid_on ?? format(new Date(), 'yyyy-MM-dd'),
    payment_type: card.payment_type ?? 'pix',
    card_id: card.card?.id ?? null,
  };
}

export function billPayload(card: BillConfirmCard, draft: BillDraft): Record<string, unknown> {
  return {
    bill_id: card.bill?.id,
    month: card.month,
    amount_cents: draft.amount_cents,
    paid_on: draft.paid_on,
    payment_type: draft.payment_type,
    card_id: draft.payment_type === 'credit_card' ? draft.card_id : null,
  };
}

/** Os erros de um item ("items.1.card_id" vira { card_id: "…" }), para mostrar embaixo de cada campo. */
export function itemErrors(fieldErrors: Record<string, string[]>, index: number): Record<string, string> {
  const prefix = `items.${index}.`;
  const errors: Record<string, string> = {};

  for (const [field, messages] of Object.entries(fieldErrors)) {
    if (field.startsWith(prefix) && messages[0]) {
      errors[field.slice(prefix.length)] = messages[0];
    }
  }

  return errors;
}

/** O primeiro item com erro: ele abre sozinho para a pessoa ver o que corrigir. */
export function firstItemWithError(fieldErrors: Record<string, string[]>): number | null {
  const indexes = Object.keys(fieldErrors)
    .map((field) => /^items\.(\d+)\./.exec(field)?.[1])
    .filter((index): index is string => index !== undefined)
    .map(Number);

  return indexes.length > 0 ? Math.min(...indexes) : null;
}

/** Pendente há mais de 30 minutos: o card já mostra "expirou", sem esperar o servidor. */
export function isExpired(action: CardAction, now: number = Date.now()): boolean {
  return action.status === 'pending' && action.expires_at !== null && parseISO(action.expires_at).getTime() <= now;
}

/** Quantos segundos ainda dá para desfazer (0 quando não dá mais, ou nunca deu). */
export function undoSecondsLeft(action: CardAction, now: number = Date.now()): number {
  if (action.status !== 'executed' || action.tier !== 'immediate' || action.undo_until === null) {
    return 0;
  }

  return Math.max(0, Math.ceil((parseISO(action.undo_until).getTime() - now) / 1000));
}

/**
 * O título e as linhas do card de "feito" (lista, lembrete e os cadastros), como no esboço:
 * "Conta fixa: Internet" e embaixo "R$ 99,90 · vence todo dia 10".
 */
export function doneCardText(card: DoneActionCard): { title: string; lines: string[] } {
  switch (card.type) {
    case 'reminder_created': {
      const when = format(parseISO(card.reminder.remind_at), "dd/MM 'às' HH:mm");
      const repeats = recurrenceLabel(card.reminder.recurrence, card.reminder.every);

      return { title: `Lembrete: ${card.reminder.text}`, lines: [[when, repeats].filter(Boolean).join(' · ')] };
    }

    case 'bill_created': {
      const { bill } = card;
      const amount = !bill.amount_varies
        ? formatCents(bill.expected_cents ?? 0)
        : bill.expected_cents !== null
          ? `~${formatCents(bill.expected_cents)} (varia)`
          : 'Valor variável';

      return {
        title: `Conta fixa: ${bill.name}`,
        lines: [[amount, `vence todo dia ${bill.due_day}`, bill.autopay ? 'débito automático' : null].filter(Boolean).join(' · ')],
      };
    }

    case 'income_created': {
      const { income } = card;
      const first = card.launched_today
        ? 'A de hoje já entrou'
        : income.first_on
          ? `A primeira cai em ${shortDate(income.first_on)}`
          : null;

      return {
        title: `Receita fixa: ${income.description}`,
        lines: [`${formatCents(income.amount_cents)} todo dia ${income.day_of_month}`, first].filter(
          (line): line is string => line !== null,
        ),
      };
    }

    case 'budget_set':
      return {
        title: `Orçamento de ${card.category?.name ?? 'categoria'}: ${formatCents(card.limit_cents)} por mês`,
        lines: [
          `${card.previous_cents !== null ? `Antes: ${formatCents(card.previous_cents)}` : 'Antes: sem orçamento'} · gasto este mês: ${formatCents(card.spent_cents)}`,
        ],
      };

    case 'list_created':
      return { title: `Lista ${card.list.name} criada`, lines: card.items.length > 0 ? [card.items.join(' · ')] : [] };

    case 'list_update':
      return listUpdateText(card);
  }
}

/** "2 itens na lista Mercado" e embaixo os itens, o que já estava e o que não foi achado. */
function listUpdateText(card: ListUpdateCard): { title: string; lines: string[] } {
  const list = card.list?.name ?? 'lista';
  const lines = [
    card.added.length > 0 ? card.added.join(' · ') : null,
    card.checked.length > 0 ? card.checked.join(' · ') : null,
    card.already.length > 0 ? `Já estava${card.already.length > 1 ? 'm' : ''}: ${card.already.join(', ')}` : null,
    card.not_found.length > 0 ? `Não achei: ${card.not_found.join(', ')}` : null,
  ].filter((line): line is string => line !== null);

  const title =
    card.added.length > 0
      ? `${plural(card.added.length, 'item', 'itens')} na lista ${list}`
      : card.checked.length > 0
        ? `${plural(card.checked.length, 'item pego', 'itens pegos')} na lista ${list}`
        : `Nada mudou na lista ${list}`;

  return { title, lines };
}

function plural(count: number, one: string, many: string): string {
  return `${count} ${count === 1 ? one : many}`;
}

/** O rótulo da ficha de conferir: "1 gasto", "2 gastos", "1 receita" ou, misturado, "3 lançamentos". */
export function itemsCountLabel(kinds: CategoryKind[]): string {
  const count = kinds.length;

  if (kinds.every((kind) => kind === 'expense')) {
    return count === 1 ? '1 gasto' : `${count} gastos`;
  }

  if (kinds.every((kind) => kind === 'income')) {
    return count === 1 ? '1 receita' : `${count} receitas`;
  }

  return `${count} lançamentos`;
}
