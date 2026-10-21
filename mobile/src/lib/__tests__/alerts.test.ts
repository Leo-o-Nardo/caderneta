import { alertTimeLabel, billAlertAmount, weekRangeLabel, weeklyChange } from '../alerts';

describe('alertTimeLabel', () => {
  // Horários sem fuso: valem no fuso de quem roda o teste, como no celular.
  const now = new Date(2026, 9, 9, 10, 0);

  it('says the time today, tomorrow or the day', () => {
    expect(alertTimeLabel('2026-10-09T15:00:00', now)).toBe('às 15:00');
    expect(alertTimeLabel('2026-10-10T09:00:00', now)).toBe('amanhã às 09:00');
    expect(alertTimeLabel('2026-10-20T09:00:00', now)).toBe('20/10 às 09:00');
  });
});

describe('billAlertAmount', () => {
  it('shows the amount, the estimate or that it is missing', () => {
    expect(billAlertAmount({ amount_cents: 9990, is_estimate: false })).toBe('R$ 99,90');
    expect(billAlertAmount({ amount_cents: 17000, is_estimate: true })).toBe('~R$ 170,00');
    expect(billAlertAmount({ amount_cents: null, is_estimate: false })).toBe('Valor a informar');
  });
});

describe('weeklyChange', () => {
  it('compares with the week before, rounding like the alert text', () => {
    expect(weeklyChange(35000, 40000)).toEqual({ text: '13% a menos que a semana anterior (R$ 400,00)', lower: true });
    expect(weeklyChange(45000, 40000)).toEqual({ text: '13% a mais que a semana anterior (R$ 400,00)', lower: false });
    expect(weeklyChange(40100, 40000)).toEqual({ text: 'O mesmo que a semana anterior (R$ 400,00)', lower: false });
  });

  it('has nothing to compare when one of the weeks had no spending', () => {
    expect(weeklyChange(0, 40000)).toBeNull();
    expect(weeklyChange(35000, 0)).toBeNull();
  });
});

describe('weekRangeLabel', () => {
  it('shows the month once, unless the week crosses it', () => {
    expect(weekRangeLabel('2026-10-12', '2026-10-18')).toBe('12 a 18/10');
    expect(weekRangeLabel('2026-09-28', '2026-10-04')).toBe('28/09 a 04/10');
  });
});
