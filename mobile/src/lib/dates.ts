import { addMonths, format, isSameDay, parseISO, subDays } from 'date-fns';
import { ptBR } from 'date-fns/locale';

/*
 * A API manda datas como "2026-10-07" e meses como "2026-10", sem fuso: são datas do calendário
 * de quem usa o app. Aqui elas são lidas sempre como meia-noite local, nunca como UTC.
 */

/** "Bom dia" das 5h às 11h, "Boa tarde" das 12h às 17h e "Boa noite" no resto. */
export function greeting(now: Date = new Date()): string {
  const hour = now.getHours();

  if (hour >= 5 && hour < 12) {
    return 'Bom dia';
  }

  return hour >= 12 && hour < 18 ? 'Boa tarde' : 'Boa noite';
}

/** O primeiro nome, para a saudação: "Leonardo Ferreira" → "Leonardo". */
export function firstName(name: string): string {
  return name.trim().split(/\s+/)[0] ?? name;
}

/** Date → "2026-10-07". */
export function toISODate(date: Date): string {
  return format(date, 'yyyy-MM-dd');
}

export function todayISO(now: Date = new Date()): string {
  return toISODate(now);
}

/** Date → "2026-10". */
export function toMonth(date: Date): string {
  return format(date, 'yyyy-MM');
}

/** "2026-10" + 1 → "2026-11". */
export function shiftMonth(month: string, amount: number): string {
  return toMonth(addMonths(parseISO(`${month}-01`), amount));
}

/** "2026-10" → "Outubro 2026". */
export function monthLabel(month: string): string {
  return capitalize(format(parseISO(`${month}-01`), 'MMMM yyyy', { locale: ptBR }));
}

/** "2026-10" → "outubro" (para frases como "fatura de outubro"). */
export function monthName(month: string): string {
  return format(parseISO(`${month}-01`), 'MMMM', { locale: ptBR });
}

/** "2026-10" → "Outubro" (o título do mês, com o ano em cima). */
export function monthTitle(month: string): string {
  return capitalize(monthName(month));
}

// O "EEE" do date-fns em português sai como "segunda", não "seg": a abreviação fica aqui.
const WEEKDAYS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

// E o "EEEE" sai como "segunda-feira": no topo do Início fica só "Segunda".
const WEEKDAYS_LONG = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

/** A data por extenso do topo do Início: "Segunda, 19 de outubro". */
export function fullDayLabel(date: Date): string {
  return `${WEEKDAYS_LONG[date.getDay()]}, ${format(date, "d 'de' MMMM", { locale: ptBR })}`;
}

/** "2026-10-07" → "Hoje", "Ontem" ou "ter, 07/10". */
export function dayLabel(date: string, now: Date = new Date()): string {
  const day = parseISO(date);

  if (isSameDay(day, now)) {
    return 'Hoje';
  }

  if (isSameDay(day, subDays(now, 1))) {
    return 'Ontem';
  }

  return `${weekday(day)}, ${format(day, 'dd/MM')}`;
}

/** O dia da semana abreviado: "seg", "ter"… */
export function weekday(date: Date): string {
  return WEEKDAYS[date.getDay()];
}

/** "2026-10-07" → "07/10". */
export function shortDate(date: string): string {
  return format(parseISO(date), 'dd/MM');
}

function capitalize(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1);
}

/** O título de um dia na lista de lançamentos: "Hoje", "Ontem" ou "Domingo, 18 de outubro". */
export function dayHeading(date: string, now: Date = new Date()): string {
  const day = parseISO(date);

  if (isSameDay(day, now)) {
    return 'Hoje';
  }

  return isSameDay(day, subDays(now, 1)) ? 'Ontem' : fullDayLabel(day);
}
