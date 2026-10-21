import { recurrenceLabel, reminderWhen } from '../reminders';

describe('recurrenceLabel', () => {
  it('says how often a reminder repeats', () => {
    expect(recurrenceLabel('none', 1)).toBeNull();
    expect(recurrenceLabel('yearly', 1)).toBe('todo ano');
    expect(recurrenceLabel('monthly', 1)).toBe('todo mês');
    expect(recurrenceLabel('monthly', 6)).toBe('a cada 6 meses');
    expect(recurrenceLabel('weekly', 2)).toBe('a cada 2 semanas');
    expect(recurrenceLabel('daily', 1)).toBe('todo dia');
  });
});

describe('reminderWhen', () => {
  // Datas sem fuso: lidas no horário do aparelho, como a tela faz com as da API.
  const now = new Date(2026, 9, 7, 12);

  it('uses today, tomorrow and yesterday with the time', () => {
    expect(reminderWhen('2026-10-07T18:30:00', now)).toBe('hoje, 18:30');
    expect(reminderWhen('2026-10-08T09:00:00', now)).toBe('amanhã, 09:00');
    expect(reminderWhen('2026-10-06T10:00:00', now)).toBe('ontem, 10:00');
  });

  it('shows the weekday and the date for the other days', () => {
    expect(reminderWhen('2026-10-27T09:00:00', now)).toBe('ter, 27/10, 09:00');
  });
});
