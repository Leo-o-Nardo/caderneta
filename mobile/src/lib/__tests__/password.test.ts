import { passwordChecks } from '../password';

const passed = (password: string) => passwordChecks(password).filter((check) => check.ok).map((check) => check.label);

describe('passwordChecks', () => {
  it('lists the three rules in order, none met when empty', () => {
    expect(passwordChecks('').map((check) => check.label)).toEqual(['8 caracteres', 'letras', 'números']);
    expect(passed('')).toEqual([]);
  });

  it('marks each rule as it is met', () => {
    expect(passed('casa')).toEqual(['letras']);
    expect(passed('12345678')).toEqual(['8 caracteres', 'números']);
    expect(passed('casa2026')).toEqual(['8 caracteres', 'letras', 'números']);
  });

  it('counts accented letters as letters', () => {
    expect(passed('ção')).toEqual(['letras']);
  });
});
