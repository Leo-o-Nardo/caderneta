import { format, parseISO } from 'date-fns';

import type { BarChartCard, CardAction, ChatMessage } from '@/api/types';

import { dayLabel, toISODate } from './dates';

/** A resposta que está sendo escrita: a linha de status e o texto que já chegou. */
export type StreamState = {
  status: string | null;
  text: string;
  // O último pedaço aplicado; pedaços repetidos ou atrasados são ignorados.
  seq: number;
};

export const EMPTY_STREAM: StreamState = { status: null, text: '', seq: 0 };

/** Junta mais um pedaço do texto, na ordem. Se a conexão mandar de novo um pedaço velho, ignora. */
export function applyDelta(stream: StreamState | undefined, seq: number, text: string): StreamState {
  const current = stream ?? EMPTY_STREAM;

  if (seq <= current.seq) {
    return current;
  }

  // Começou a escrever: a linha de status sai.
  return { status: null, text: current.text + text, seq };
}

/** O dia da mensagem no fuso do aparelho ("2026-10-09"), para agrupar por dia. */
export function messageDay(message: Pick<ChatMessage, 'created_at'>): string {
  return toISODate(parseISO(message.created_at));
}

/** "Hoje", "Ontem", "seg, 05/10": o separador acima das mensagens de cada dia. */
export function messageDayLabel(message: Pick<ChatMessage, 'created_at'>, now: Date = new Date()): string {
  return dayLabel(messageDay(message), now);
}

/** "19:05". */
export function messageTime(message: Pick<ChatMessage, 'created_at'>): string {
  return format(parseISO(message.created_at), 'HH:mm');
}

/** A duração de uma gravação: 8000 → "0:08"; 65000 → "1:05". */
export function formatDuration(ms: number): string {
  const seconds = Math.max(0, Math.floor(ms / 1000));

  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

/**
 * Se a mensagem de índice $index abre um dia. A lista vem da mais nova para a mais antiga: abre o
 * dia quando é a mais antiga carregada ou quando a anterior (índice seguinte) é de outro dia.
 */
export function startsDay(messages: Pick<ChatMessage, 'created_at'>[], index: number): boolean {
  const older = messages[index + 1];

  return older === undefined || messageDay(older) !== messageDay(messages[index]);
}

/** A largura de cada barra, em %, em relação à maior. Valor zero ainda aparece como um traço. */
export function barWidths(bars: BarChartCard['bars']): number[] {
  const max = Math.max(...bars.map((bar) => bar.value_cents), 1);

  return bars.map((bar) => Math.max(2, Math.round((bar.value_cents / max) * 100)));
}

/** O que aconteceu com a ação, como o card mostra: "Confirmado", "Feito", "Expirou"… */
export function actionStatusLabel(action: CardAction): string {
  switch (action.status) {
    case 'pending':
      return action.tier === 'explicit' ? 'Esperando você confirmar a exclusão' : 'Esperando você conferir';
    case 'executed':
      return action.tier === 'immediate' ? 'Feito' : action.tier === 'explicit' ? 'Apagado' : 'Confirmado';
    case 'cancelled':
      return 'Cancelado';
    case 'expired':
      return 'Expirou sem confirmação';
    case 'undone':
      return 'Desfeito';
  }
}
