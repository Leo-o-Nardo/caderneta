import { useEffect, useState } from 'react';

import { todayISO, toMonth } from '@/lib/dates';
import { queryClient } from '@/lib/queryClient';
import { useSession } from '@/stores/session';

import { unreadAlertsQuery } from './alerts';
import { meQuery } from './auth';
import { summaryQuery } from './summary';
import { transactionsQuery, type TransactionFilters } from './transactions';

/** Os lançamentos de hoje no Início: os 20 primeiros bastam para a lista e o total do dia. */
export function todaysFilters(today: string): TransactionFilters {
  return { from: today, to: today, per_page: 20 };
}

/**
 * Busca de uma vez o que aparece ao abrir o app: o nome, o resumo do mês, os lançamentos de hoje e
 * os avisos não lidos (o número da aba). Vai para o mesmo cache das telas, que então não buscam de
 * novo. Não falha: o prefetch do TanStack guarda o erro no cache, e a própria tela o mostra.
 */
export async function prefetchHome(now: Date = new Date()): Promise<void> {
  await Promise.all([
    queryClient.prefetchQuery(meQuery),
    queryClient.prefetchQuery(summaryQuery(toMonth(now))),
    queryClient.prefetchInfiniteQuery(transactionsQuery(todaysFilters(todayISO(now)))),
    queryClient.prefetchQuery(unreadAlertsQuery),
  ]);
}

/**
 * Se a primeira tela já tem os dados. Com login, busca os do Início (prefetchHome) e fica pronto quando
 * chegam; sem login, a capa de entrada não depende de dado nenhum. A abertura (OpeningSplash) espera
 * por isto, com um limite, para abrir a capa sobre a tela completa.
 */
export function useHomeReady(): boolean {
  const status = useSession((state) => state.status);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    if (status !== 'signedIn' || loaded) {
      return;
    }

    let active = true;

    prefetchHome().then(() => {
      if (active) {
        setLoaded(true);
      }
    });

    return () => {
      active = false;
    };
  }, [status, loaded]);

  return status === 'signedOut' || loaded;
}
