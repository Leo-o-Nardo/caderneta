import { formatCents } from './money';

/**
 * A legenda do saldo do mês no Resumo: como ele é calculado e quanto da receita foi gasto.
 * "Receitas menos despesas. Você gastou 41% do que recebeu." No vermelho: "Você gastou
 * R$ 318,40 a mais do que recebeu."
 */
export function balanceCaption(incomeCents: number, expenseCents: number): string {
  if (expenseCents > incomeCents) {
    return `Você gastou ${formatCents(expenseCents - incomeCents)} a mais do que recebeu.`;
  }

  if (incomeCents === 0) {
    return 'Receitas menos despesas.';
  }

  return `Receitas menos despesas. Você gastou ${Math.round((expenseCents * 100) / incomeCents)}% do que recebeu.`;
}
