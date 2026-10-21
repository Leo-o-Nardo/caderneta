import { addMonths, format, getDaysInMonth, parseISO, setDate, startOfMonth } from 'date-fns';

/**
 * O primeiro dia em que cai uma receita fixa cadastrada hoje, a mesma conta da API: neste mês, se
 * o dia ainda não passou (hoje conta); senão, no próximo. Dia 31 vira o último dia nos meses curtos.
 * "today" e a resposta no formato "2026-10-07".
 */
export function firstIncomeDate(day: number, today: string): string {
  const now = parseISO(today);
  const thisMonth = dayIn(startOfMonth(now), day);
  const first = thisMonth < now ? dayIn(addMonths(startOfMonth(now), 1), day) : thisMonth;

  return format(first, 'yyyy-MM-dd');
}

function dayIn(month: Date, day: number): Date {
  return setDate(month, Math.min(day, getDaysInMonth(month)));
}
