import { format, isSameDay, parseISO, subDays } from 'date-fns';

import type { Recurrence } from '@/api/types';

import { weekday } from './dates';

const UNITS: Record<Exclude<Recurrence, 'none'>, [single: string, plural: string]> = {
  daily: ['todo dia', 'dias'],
  weekly: ['toda semana', 'semanas'],
  monthly: ['todo mês', 'meses'],
  yearly: ['todo ano', 'anos'],
};

/** "todo ano", "a cada 6 meses"; null quando não se repete. */
export function recurrenceLabel(recurrence: Recurrence, every: number): string | null {
  if (recurrence === 'none') {
    return null;
  }

  const [single, plural] = UNITS[recurrence];

  return every === 1 ? single : `a cada ${every} ${plural}`;
}

/** Quando o lembrete toca, no fuso do aparelho: "hoje, 10:00", "amanhã, 09:00", "qua, 27/10, 09:00". */
export function reminderWhen(remindAt: string, now: Date = new Date()): string {
  const date = parseISO(remindAt);
  const time = format(date, 'HH:mm');

  if (isSameDay(date, now)) {
    return `hoje, ${time}`;
  }

  if (isSameDay(date, subDays(now, -1))) {
    return `amanhã, ${time}`;
  }

  if (isSameDay(date, subDays(now, 1))) {
    return `ontem, ${time}`;
  }

  return `${weekday(date)}, ${format(date, 'dd/MM')}, ${time}`;
}
