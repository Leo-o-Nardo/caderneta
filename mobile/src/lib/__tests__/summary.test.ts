import { balanceCaption } from '../summary';

describe('balanceCaption', () => {
  it('says how much of the income was spent', () => {
    expect(balanceCaption(985000, 408430)).toBe('Receitas menos despesas. Você gastou 41% do que recebeu.');
  });

  it('says how much more was spent when the balance is negative', () => {
    expect(balanceCaption(100000, 131840)).toBe('Você gastou R$ 318,40 a mais do que recebeu.');
    expect(balanceCaption(0, 5000)).toBe('Você gastou R$ 50,00 a mais do que recebeu.');
  });

  it('only explains the math when nothing came in or went out', () => {
    expect(balanceCaption(0, 0)).toBe('Receitas menos despesas.');
  });
});
