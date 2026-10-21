import { firstIncomeDate } from '../recurringIncomes';

describe('firstIncomeDate', () => {
  it('starts this month while the day has not passed, today included', () => {
    expect(firstIncomeDate(15, '2026-10-07')).toBe('2026-10-15');
    expect(firstIncomeDate(7, '2026-10-07')).toBe('2026-10-07');
  });

  it('starts next month when the day already passed', () => {
    expect(firstIncomeDate(5, '2026-10-07')).toBe('2026-11-05');
    expect(firstIncomeDate(1, '2026-12-20')).toBe('2027-01-01');
  });

  it('moves day 31 to the last day of short months', () => {
    expect(firstIncomeDate(31, '2026-02-10')).toBe('2026-02-28');
    expect(firstIncomeDate(31, '2026-10-31')).toBe('2026-10-31');
    expect(firstIncomeDate(30, '2027-01-31')).toBe('2027-02-28');
  });
});
