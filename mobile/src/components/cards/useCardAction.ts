import * as Haptics from 'expo-haptics';
import { useEffect, useState } from 'react';

import type { CardAction } from '@/api/types';
import { undoSecondsLeft } from '@/lib/actions';
import { ApiError } from '@/lib/apiError';

type Busy = 'confirm' | 'cancel' | 'undo' | null;

/**
 * O estado dos botões de um card: qual está carregando e o erro da última tentativa. Deu certo,
 * uma vibração curta, como ao marcar um item da lista.
 */
export function useCardAction() {
  const [busy, setBusy] = useState<Busy>(null);
  const [error, setError] = useState<ApiError | null>(null);

  /** Roda o botão. Devolve o erro (ou null), para o card reagir na hora (abrir o item com erro). */
  async function run(kind: Exclude<Busy, null>, task: () => Promise<void>): Promise<ApiError | null> {
    setBusy(kind);
    setError(null);

    try {
      await task();

      if (kind !== 'cancel') {
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      }

      return null;
    } catch (caught) {
      const failure = caught instanceof ApiError ? caught : new ApiError(0, 'Não deu certo. Tente de novo.');
      setError(failure);

      return failure;
    } finally {
      setBusy(null);
    }
  }

  return { busy, error, run };
}

/** Os segundos que ainda dá para desfazer, contando de 1 em 1 enquanto houver tempo. */
export function useUndoSecondsLeft(action: CardAction): number {
  const [now, setNow] = useState(() => Date.now());
  const left = undoSecondsLeft(action, now);
  const counting = left > 0;

  useEffect(() => {
    if (!counting) {
      return;
    }

    const timer = setInterval(() => setNow(Date.now()), 1000);

    return () => clearInterval(timer);
  }, [counting]);

  return left;
}
