import { amountLabel, centsFromDigits, formatCents } from '../money';

describe('formatCents', () => {
  it('formats reais and centavos', () => {
    expect(formatCents(4500)).toBe('R$ 45,00');
    expect(formatCents(5)).toBe('R$ 0,05');
    expect(formatCents(0)).toBe('R$ 0,00');
  });

  it('groups the thousands with dots', () => {
    expect(formatCents(123456789)).toBe('R$ 1.234.567,89');
    expect(formatCents(100000)).toBe('R$ 1.000,00');
  });

  it('shows the sign when asked or when negative', () => {
    expect(formatCents(-11280)).toBe('-R$ 112,80');
    expect(formatCents(500, { sign: true })).toBe('+R$ 5,00');
    expect(formatCents(0, { sign: true })).toBe('R$ 0,00');
  });
});

describe('amountLabel', () => {
  it('puts "+" only on income', () => {
    expect(amountLabel(65000, { income: true })).toBe('+R$ 650,00');
    expect(amountLabel(4200)).toBe('R$ 42,00');
  });

  it('marks estimates with "~" and keeps the minus of a negative balance', () => {
    expect(amountLabel(18900, { estimate: true })).toBe('~R$ 189,00');
    expect(amountLabel(-31840)).toBe('-R$ 318,40');
  });
});

describe('centsFromDigits', () => {
  it('reads the digits like a bank app, the last two being the centavos', () => {
    expect(centsFromDigits('4500')).toBe(4500);
    expect(centsFromDigits('R$ 1.234,56')).toBe(123456);
    expect(centsFromDigits('')).toBe(0);
  });
});
