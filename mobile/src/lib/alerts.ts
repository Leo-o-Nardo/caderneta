import { addDays, format, isSameDay, parseISO } from 'date-fns';

import type { BillDueCard } from '@/api/types';

import { formatCents } from './money';

/** Quando é um horário de aviso, como se fala: "às 15:00" (hoje), "amanhã às 09:00", "20/10 às 09:00". */
export function alertTimeLabel(iso: string, now: Date = new Date()): string {
  const date = parseISO(iso);
  const time = format(date, 'HH:mm');

  if (isSameDay(date, now)) {
    return `às ${time}`;
  }

  if (isSameDay(date, addDays(now, 1))) {
    return `amanhã às ${time}`;
  }

  return `${format(date, 'dd/MM')} às ${time}`;
}

/** O valor no card de conta: "R$ 99,90", "~R$ 170,00" (estimativa) ou "Valor a informar". */
export function billAlertAmount(card: Pick<BillDueCard, 'amount_cents' | 'is_estimate'>): string {
  if (card.amount_cents === null) {
    return 'Valor a informar';
  }

  return `${card.is_estimate ? '~' : ''}${formatCents(card.amount_cents)}`;
}

/**
 * A comparação do resumo da semana: "13% a menos que a semana anterior (R$ 400,00)". Null quando
 * não há o que comparar (uma das semanas sem gasto). lower: gastou menos (a frase fica verde).
 */
export function weeklyChange(spentCents: number, previousCents: number): { text: string; lower: boolean } | null {
  if (spentCents === 0 || previousCents === 0) {
    return null;
  }

  // Igual ao texto do aviso, que vem do backend: arredonda o meio para cima (12,5% → 13%).
  const change = Math.round((Math.abs(spentCents - previousCents) * 100) / previousCents);
  const previous = formatCents(previousCents);

  if (change === 0) {
    return { text: `O mesmo que a semana anterior (${previous})`, lower: false };
  }

  return spentCents < previousCents
    ? { text: `${change}% a menos que a semana anterior (${previous})`, lower: true }
    : { text: `${change}% a mais que a semana anterior (${previous})`, lower: false };
}

/** A semana do resumo: "12 a 18/10", ou "28/09 a 04/10" quando vira o mês. */
export function weekRangeLabel(from: string, to: string): string {
  const start = parseISO(from);
  const end = parseISO(to);

  return `${format(start, start.getMonth() === end.getMonth() ? 'dd' : 'dd/MM')} a ${format(end, 'dd/MM')}`;
}
