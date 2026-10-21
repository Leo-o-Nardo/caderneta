import { format, parseISO } from 'date-fns';

/**
 * A mesma regra do InvoiceCalendar do backend (os testes usam os mesmos casos), para o formulário
 * mostrar a fatura na hora, sem chamar a API: a compra feita antes do dia de fechamento entra na
 * fatura que fecha naquele mês; no dia do fechamento ou depois, na seguinte. Dia que não existe no
 * mês (31 em fevereiro) cai no último dia.
 *
 * Devolve o mês da fatura: "2026-11".
 */
export function invoiceMonthFor(closingDay: number, purchasedOn: string): string {
  const purchase = parseISO(purchasedOn);
  let closesOn = dayIn(purchase.getFullYear(), purchase.getMonth(), closingDay);

  if (purchase >= closesOn) {
    closesOn = dayIn(purchase.getFullYear(), purchase.getMonth() + 1, closingDay);
  }

  return format(closesOn, 'yyyy-MM');
}

function dayIn(year: number, monthIndex: number, day: number): Date {
  // O dia 0 do mês seguinte é o último dia deste mês.
  const lastDay = new Date(year, monthIndex + 1, 0).getDate();

  return new Date(year, monthIndex, Math.min(day, lastDay));
}
