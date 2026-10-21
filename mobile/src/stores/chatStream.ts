import { create } from 'zustand';

import { applyDelta, EMPTY_STREAM, type StreamState } from '@/lib/chat';

type ChatStreamState = {
  // Por id da resposta que está "pensando": a linha de status e o texto que já chegou.
  streams: Record<number, StreamState>;
  setStatus: (messageId: number, text: string) => void;
  appendDelta: (messageId: number, seq: number, text: string) => void;
  // A resposta ficou pronta (ou falhou): o texto salvo, que veio no evento, toma o lugar do parcial.
  finish: (messageId: number) => void;
  clear: () => void;
};

/**
 * O que ainda não está salvo das respostas em andamento. Fica fora do cache da API porque muda
 * várias vezes por segundo; quando a resposta termina, o cache recebe a mensagem pronta.
 */
export const useChatStream = create<ChatStreamState>((set) => ({
  streams: {},

  setStatus: (messageId, text) =>
    set((state) => ({
      streams: { ...state.streams, [messageId]: { ...(state.streams[messageId] ?? EMPTY_STREAM), status: text } },
    })),

  appendDelta: (messageId, seq, text) =>
    set((state) => ({
      streams: { ...state.streams, [messageId]: applyDelta(state.streams[messageId], seq, text) },
    })),

  finish: (messageId) =>
    set((state) => {
      const streams = { ...state.streams };
      delete streams[messageId];

      return { streams };
    }),

  clear: () => set({ streams: {} }),
}));
