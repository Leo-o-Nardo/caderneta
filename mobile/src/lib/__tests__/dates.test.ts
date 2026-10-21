import {
  dayHeading,
  dayLabel,
  firstName,
  fullDayLabel,
  greeting,
  monthLabel,
  monthName,
  monthTitle,
  shiftMonth,
  shortDate,
} from '../dates';

describe('greeting', () => {
  it('changes with the time of day', () => {
    expect(greeting(new Date(2026, 9, 7, 8))).toBe('Bom dia');
    expect(greeting(new Date(2026, 9, 7, 13))).toBe('Boa tarde');
    expect(greeting(new Date(2026, 9, 7, 20))).toBe('Boa noite');
    expect(greeting(new Date(2026, 9, 7, 2))).toBe('Boa noite');
  });
});

describe('firstName', () => {
  it('keeps only the first name', () => {
    expect(firstName('Leonardo Ferreira de Souza')).toBe('Leonardo');
    expect(firstName('  Ana ')).toBe('Ana');
  });
});

describe('months', () => {
  it('moves between months across the year', () => {
    expect(shiftMonth('2026-10', 1)).toBe('2026-11');
    expect(shiftMonth('2026-12', 1)).toBe('2027-01');
    expect(shiftMonth('2026-01', -1)).toBe('2025-12');
  });

  it('names the months in Portuguese', () => {
    expect(monthLabel('2026-10')).toBe('Outubro 2026');
    expect(monthName('2026-11')).toBe('novembro');
    expect(monthTitle('2026-03')).toBe('Março');
  });
});

describe('fullDayLabel', () => {
  it('writes the weekday without "-feira" and the day of the month', () => {
    expect(fullDayLabel(new Date(2026, 9, 19))).toBe('Segunda, 19 de outubro');
    expect(fullDayLabel(new Date(2026, 9, 18))).toBe('Domingo, 18 de outubro');
    expect(fullDayLabel(new Date(2026, 9, 1))).toBe('Quinta, 1 de outubro');
  });
});

describe('dayLabel', () => {
  const now = new Date(2026, 9, 7, 15);

  it('says today and yesterday', () => {
    expect(dayLabel('2026-10-07', now)).toBe('Hoje');
    expect(dayLabel('2026-10-06', now)).toBe('Ontem');
  });

  it('shows the weekday and the date for older days', () => {
    expect(dayLabel('2026-10-05', now)).toMatch(/^seg, 05\/10$/);
    expect(shortDate('2026-10-05')).toBe('05/10');
  });
});

describe('dayHeading', () => {
  const now = new Date(2026, 9, 19, 15);

  it('says today and yesterday, and writes the other days in full', () => {
    expect(dayHeading('2026-10-19', now)).toBe('Hoje');
    expect(dayHeading('2026-10-18', now)).toBe('Ontem');
    expect(dayHeading('2026-10-15', now)).toBe('Quinta, 15 de outubro');
  });
});
