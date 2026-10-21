import type { CardAction } from '@/api/types';

import { actionStatusLabel, applyDelta, barWidths, formatDuration, startsDay } from '../chat';

describe('applyDelta', () => {
  it('joins the pieces in order and drops the status line', () => {
    let stream = applyDelta({ status: 'Consultando lançamentos…', text: '', seq: 0 }, 1, 'Em outubro, ');
    stream = applyDelta(stream, 2, 'foram R$ 400,00.');

    expect(stream).toEqual({ status: null, text: 'Em outubro, foram R$ 400,00.', seq: 2 });
  });

  it('ignores a piece that arrives again or late', () => {
    const stream = applyDelta(applyDelta(undefined, 1, 'Oi'), 2, ', Leo');

    expect(applyDelta(stream, 2, ', Leo')).toBe(stream);
    expect(applyDelta(stream, 1, 'Oi')).toBe(stream);
  });
});

describe('startsDay', () => {
  // Da mais nova para a mais antiga, como a lista do chat.
  const messages = [
    { created_at: '2026-10-09T15:05:00-03:00' },
    { created_at: '2026-10-09T09:00:00-03:00' },
    { created_at: '2026-10-08T20:00:00-03:00' },
  ];

  it('opens a day on the oldest message of each day', () => {
    expect(startsDay(messages, 0)).toBe(false);
    expect(startsDay(messages, 1)).toBe(true);
    expect(startsDay(messages, 2)).toBe(true);
  });
});

describe('barWidths', () => {
  it('is relative to the biggest bar, with a minimum to stay visible', () => {
    const bars = [40000, 20000, 0].map((value_cents) => ({ label: 'x', value_cents, value: '', color: null }));

    expect(barWidths(bars)).toEqual([100, 50, 2]);
  });
});

describe('actions', () => {
  const action = (tier: CardAction['tier'], status: CardAction['status']): CardAction => ({
    id: '01j',
    tool: 'x',
    tier,
    status,
    expires_at: null,
    undo_until: null,
  });

  it('says what happened depending on the kind of action', () => {
    expect(actionStatusLabel(action('confirm', 'executed'))).toBe('Confirmado');
    expect(actionStatusLabel(action('immediate', 'executed'))).toBe('Feito');
    expect(actionStatusLabel(action('explicit', 'executed'))).toBe('Apagado');
    expect(actionStatusLabel(action('immediate', 'undone'))).toBe('Desfeito');
  });
});

describe('formatDuration', () => {
  it('shows minutes and seconds, like a recording timer', () => {
    expect(formatDuration(0)).toBe('0:00');
    expect(formatDuration(8400)).toBe('0:08');
    expect(formatDuration(60000)).toBe('1:00');
    expect(formatDuration(65000)).toBe('1:05');
  });
});
