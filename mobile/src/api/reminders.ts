import { useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';

import { keys } from './keys';
import type { Recurrence, Reminder, Visibility } from './types';

export type ReminderInput = {
  text: string;
  // Horário local: "2026-10-20 09:00" (ou só "2026-10-20", que vira 9h).
  remind_at: string;
  recurrence: Recurrence;
  every: number;
  visibility: Visibility;
};

/** Os lembretes ativos, do mais próximo ao mais distante (ou os concluídos, com status "done"). */
export function useReminders(status: 'upcoming' | 'done' = 'upcoming') {
  return useQuery({
    queryKey: keys.reminders(status),
    queryFn: () => api<Reminder[]>(`/reminders?status=${status}`),
  });
}

export function createReminder(input: ReminderInput): Promise<Reminder> {
  return api('/reminders', { method: 'POST', body: input });
}

export function updateReminder(id: number, input: ReminderInput): Promise<Reminder> {
  return api(`/reminders/${id}`, { method: 'PATCH', body: input });
}

export function deleteReminder(id: number): Promise<void> {
  return api(`/reminders/${id}`, { method: 'DELETE' });
}

/** "Feito": sem repetição, conclui; com repetição, pula para a próxima data. */
export function completeReminder(id: number): Promise<Reminder> {
  return api(`/reminders/${id}/done`, { method: 'POST' });
}

/** "Adiar": sem data, 24 horas; com data, para ela ("2026-10-25" ou "2026-10-25 18:00"). */
export function snoozeReminder(id: number, until?: string): Promise<Reminder> {
  return api(`/reminders/${id}/snooze`, { method: 'POST', body: until ? { until } : {} });
}
