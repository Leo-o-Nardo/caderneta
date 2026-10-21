import { useInfiniteQuery, type InfiniteData } from '@tanstack/react-query';

import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';
import { realtime, useRealtimeStatus } from '@/lib/realtime';
import { useActionDrafts } from '@/stores/actionDrafts';
import { useChatStream } from '@/stores/chatStream';

import { ME_KEY } from './auth';
import { invalidateFinance, keys } from './keys';
import { invalidateLists } from './lists';
import { toQuery } from './query';
import type { ActionCard, ChatCard, ChatMessage, CursorPage, User } from './types';

type Pages = InfiniteData<CursorPage<ChatMessage>, string | null>;

/**
 * A conversa, da mensagem mais nova para a mais antiga, 30 por vez. Enquanto alguma resposta está
 * "pensando" (ou uma voz "transcrevendo"), o chat também busca sozinho: de 2 em 2 segundos sem o
 * WebSocket e, com ele, de 4 em 4, para o caso de algum evento se perder no caminho. Tudo pronto,
 * a busca para.
 */
export function useConversation({ connected }: { connected: boolean }) {
  return useInfiniteQuery({
    queryKey: keys.conversation,
    queryFn: ({ pageParam }) => api<CursorPage<ChatMessage>>(`/conversation?${toQuery({ cursor: pageParam })}`),
    initialPageParam: null as string | null,
    getNextPageParam: (page) => page.meta.next_cursor,
    refetchInterval: (query) => (hasPending(query.state.data) ? (connected ? 4000 : 2000) : false),
  });
}

function hasPending(data: Pages | undefined): boolean {
  return (
    data?.pages.some((page) => page.data.some((message) => message.status === 'pending' || message.status === 'transcribing')) ??
    false
  );
}

/** Manda a mensagem. A pergunta e a resposta vazia ("pensando") entram na conversa na hora. */
export async function sendMessage(text: string, source: 'chat' | 'home' = 'chat'): Promise<void> {
  const { message, reply } = await api<{ message: ChatMessage; reply: ChatMessage }>('/conversation/messages', {
    method: 'POST',
    body: { text, source },
  });

  addNewest([reply, message]);
}

/** Uma gravação pronta para enviar: o arquivo no aparelho e quanto tempo ela tem. */
export type VoiceRecording = { uri: string; durationMs: number };

/**
 * Manda a gravação. A mensagem entra no chat "transcrevendo" e a resposta "pensando"; o texto
 * transcrito e a resposta chegam depois, pelo canal ao vivo (ou pela busca automática).
 */
export async function sendVoice(recording: VoiceRecording, source: 'chat' | 'home' = 'chat'): Promise<void> {
  const form = new FormData();
  // No React Native, um arquivo vai no FormData como { uri, name, type }.
  form.append('audio', { uri: recording.uri, name: 'voz.m4a', type: 'audio/m4a' } as unknown as Blob);
  form.append('duration_ms', String(Math.round(recording.durationMs)));
  form.append('source', source);

  const { message, reply } = await api<{ message: ChatMessage; reply: ChatMessage }>('/conversation/voice', {
    method: 'POST',
    body: form,
  });

  addNewest([reply, message]);
}

/**
 * "Limpar conversa": a API arquiva a conversa (fica guardada) e a próxima mensagem começa outra.
 * Aqui, o chat simplesmente fica vazio.
 */
export async function clearConversation(): Promise<void> {
  await api('/conversation', { method: 'DELETE' });
  useChatStream.getState().clear();
  queryClient.setQueryData<Pages>(keys.conversation, (data) =>
    data ? { pages: [{ data: [], meta: { next_cursor: null, per_page: 30 } }], pageParams: [null] } : data,
  );
}

/**
 * Liga os eventos do canal da pessoa ao chat: status e pedaços vão para o estado da resposta em
 * andamento; a resposta pronta (ou o card que mudou) vai para o cache. Devolve como desligar.
 */
export function syncAssistantEvents(): () => void {
  const stream = useChatStream.getState;

  const stops = [
    // Um aviso do app (lembrete, conta vencendo, débito automático): entra no Chat se for da casa
    // aberta, e o número da aba Chat é buscado de novo.
    realtime.on('alert.created', (data) => {
      const { message, household_id } = data as { message: ChatMessage; household_id: number };

      if (queryClient.getQueryData<User>(ME_KEY)?.current_household?.id === household_id) {
        addNewest([message]);
      }

      queryClient.invalidateQueries({ queryKey: keys.unreadAlerts });
    }),

    // A voz virou texto (ou não deu para entender): a bolha "transcrevendo…" troca pelo resultado.
    realtime.on('message.transcribed', (data) => {
      const { message } = data as { message: ChatMessage };
      replaceMessage(message);
    }),

    realtime.on('assistant.status', (data) => {
      const { message_id, text } = data as { message_id: number; text: string };
      stream().setStatus(message_id, text);
    }),

    realtime.on('assistant.delta', (data) => {
      const { message_id, seq, text } = data as { message_id: number; seq: number; text: string };
      stream().appendDelta(message_id, seq, text);
    }),

    realtime.on('assistant.completed', (data) => {
      const { message } = data as { message: ChatMessage };
      replaceMessage(message);
      stream().finish(message.id);

      // Ações imediatas (lista, lembrete) já gravaram: as outras telas buscam de novo.
      if (message.cards.some((card) => 'action' in card && card.action.status === 'executed')) {
        refreshAfterAction();
      }
    }),

    realtime.on('assistant.failed', (data) => {
      const { message } = data as { message: ChatMessage };
      replaceMessage(message);
      stream().finish(message.id);
    }),

    realtime.on('action.updated', (data) => {
      const { message_id, card } = data as { message_id: number | null; card: ActionCard };

      if (message_id !== null) {
        replaceCard(message_id, card);
      }

      refreshAfterAction();
    }),

    // A conexão voltou: o que chegou enquanto ela estava caída se perdeu. Busca a conversa de novo.
    useRealtimeStatus.subscribe((state, previous) => {
      if (state.connected && !previous.connected) {
        queryClient.invalidateQueries({ queryKey: keys.conversation });
        queryClient.invalidateQueries({ queryKey: keys.unreadAlerts });
      }
    }),
  ];

  return () => stops.forEach((stop) => stop());
}

type ActionResponse = { message_id: number | null; card: ActionCard };

/**
 * Os botões dos cards. A resposta traz o card atualizado: ele entra no lugar, sem buscar a
 * conversa de novo, e as outras telas (Início, Resumo, Agenda, Lista) são atualizadas.
 */
export async function confirmAction(actionId: string, payload?: Record<string, unknown>): Promise<void> {
  applyActionResponse(
    await api<ActionResponse>(`/actions/${actionId}/confirm`, { method: 'POST', body: payload ? { payload } : {} }),
  );
}

export async function cancelAction(actionId: string): Promise<void> {
  applyActionResponse(await api<ActionResponse>(`/actions/${actionId}/cancel`, { method: 'POST' }));
}

export async function undoAction(actionId: string): Promise<void> {
  applyActionResponse(await api<ActionResponse>(`/actions/${actionId}/undo`, { method: 'POST' }));
}

function applyActionResponse({ message_id, card }: ActionResponse): void {
  if (message_id !== null) {
    replaceCard(message_id, card);
  }

  useActionDrafts.getState().clear(card.action.id);
  refreshAfterAction();
}

/** Troca um card de ação dentro da mensagem dele (achado pelo id da ação). */
export function replaceCard(messageId: number, card: ActionCard): void {
  updateMessages((message) =>
    message.id !== messageId
      ? message
      : {
          ...message,
          cards: message.cards.map((existing: ChatCard) =>
            'action' in existing && existing.action.id === card.action.id ? card : existing,
          ),
        },
  );
}

/** Ação gravada, cancelada ou desfeita: lançamentos, resumo, contas e listas podem ter mudado. */
function refreshAfterAction(): void {
  invalidateFinance();
  invalidateLists();
}

function addNewest(messages: ChatMessage[]): void {
  const data = queryClient.getQueryData<Pages>(keys.conversation);

  if (!data) {
    queryClient.invalidateQueries({ queryKey: keys.conversation });
    return;
  }

  const known = new Set(data.pages.flatMap((page) => page.data.map((message) => message.id)));
  const fresh = messages.filter((message) => !known.has(message.id));

  queryClient.setQueryData<Pages>(keys.conversation, {
    ...data,
    pages: data.pages.map((page, index) => (index === 0 ? { ...page, data: [...fresh, ...page.data] } : page)),
  });
}

/** Troca a mensagem pela versão salva. Se ela não está no cache (chegou antes da resposta do POST), busca tudo. */
export function replaceMessage(message: ChatMessage): void {
  const data = queryClient.getQueryData<Pages>(keys.conversation);
  const found = data?.pages.some((page) => page.data.some((existing) => existing.id === message.id));

  if (!found) {
    queryClient.invalidateQueries({ queryKey: keys.conversation });
    return;
  }

  updateMessages((existing) => (existing.id === message.id ? message : existing));
}

function updateMessages(update: (message: ChatMessage) => ChatMessage): void {
  queryClient.setQueryData<Pages>(keys.conversation, (data) =>
    data ? { ...data, pages: data.pages.map((page) => ({ ...page, data: page.data.map(update) })) } : data,
  );
}
