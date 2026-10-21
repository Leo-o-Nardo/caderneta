import type { BillMonth, BillOccurrence } from '@/api/types';
import type { ColorName } from '@/theme/tokens';

import { shortDate } from './dates';
import { formatCents } from './money';

/** O texto e a cor da situação de uma conta: "vence amanhã", "atrasada há 3 dias", "paga"... */
export function billDueLabel(bill: Pick<BillMonth, 'status' | 'days_until_due' | 'due_on'>): { text: string; tone: ColorName } {
  const days = bill.days_until_due;

  switch (bill.status) {
    case 'paid':
      return { text: 'paga', tone: 'accent' };
    case 'skipped':
      return { text: 'pulada', tone: 'faint' };
    case 'due_today':
      return { text: 'vence hoje', tone: 'warn' };
    case 'overdue':
      return { text: days === -1 ? 'venceu ontem' : `atrasada há ${-days} dias`, tone: 'danger' };
    default:
      if (days === 1) {
        return { text: 'vence amanhã', tone: 'warn' };
      }

      return { text: days <= 7 ? `vence em ${days} dias` : `vence ${shortDate(bill.due_on)}`, tone: 'muted' };
  }
}

/** O valor de um mês da conta: "R$ 620,00", "~R$ 179,00" (estimativa) ou "valor variável" (sem nenhum). */
export function billAmountLabel(bill: Pick<BillMonth, 'amount_cents' | 'is_estimate'>): string {
  if (bill.amount_cents === null) {
    return 'valor variável';
  }

  return `${bill.is_estimate ? '~' : ''}${formatCents(bill.amount_cents)}`;
}

type DueBill = Pick<BillOccurrence, 'status' | 'days_until_due' | 'due_on' | 'amount_cents' | 'is_estimate'> & {
  bill: Pick<BillOccurrence['bill'], 'name'>;
};

/**
 * O título do aviso de contas do Início (só as atrasadas e as que vencem hoje): "Luz atrasada há
 * 11 dias", "Luz e Condomínio estão atrasadas", "3 contas vencem hoje". A mesma conta pode estar
 * atrasada em dois meses: o nome aparece uma vez só.
 */
export function dueNoticeTitle(bills: DueBill[]): string {
  if (bills.length === 1) {
    return `${bills[0].bill.name} ${billDueLabel(bills[0]).text}`;
  }

  const names = [...new Set(bills.map((occurrence) => occurrence.bill.name))];

  if (names.length === 1) {
    return `${names[0]}: ${bills.length} meses em aberto`;
  }

  if (bills.every((occurrence) => occurrence.status === 'overdue')) {
    return names.length === 2 ? `${names[0]} e ${names[1]} estão atrasadas` : `${bills.length} contas atrasadas`;
  }

  if (bills.every((occurrence) => occurrence.status === 'due_today')) {
    return names.length === 2 ? `${names[0]} e ${names[1]} vencem hoje` : `${bills.length} contas vencem hoje`;
  }

  return `${bills.length} contas vencendo hoje ou atrasadas`;
}

/**
 * A legenda do aviso, com o total, curta para caber ao lado do ícone: "R$ 620,00 · ver na Agenda"
 * ou "Cerca de R$ 809,00 · ver na Agenda" (quando entra alguma estimativa ou conta sem valor).
 */
export function dueNoticeCaption(bills: DueBill[]): string {
  const known = bills.filter((occurrence) => occurrence.amount_cents !== null);

  if (known.length === 0) {
    return 'Ver na Agenda';
  }

  const total = known.reduce((sum, occurrence) => sum + (occurrence.amount_cents ?? 0), 0);
  const approximate = bills.some((occurrence) => occurrence.is_estimate || occurrence.amount_cents === null);

  return `${approximate ? 'Cerca de ' : ''}${formatCents(total)} · ver na Agenda`;
}
