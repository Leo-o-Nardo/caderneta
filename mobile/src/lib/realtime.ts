import { useEffect } from 'react';
import { AppState } from 'react-native';
import { create } from 'zustand';

import { api } from './api';
import { parseFrame, pingFrame, pongFrame, reconnectDelay, socketUrl, subscribeFrame } from './pusherProtocol';

const URL = process.env.EXPO_PUBLIC_REVERB_URL ?? 'ws://10.0.2.2:8180';
const KEY = process.env.EXPO_PUBLIC_REVERB_KEY ?? '';

// Sem nenhuma mensagem nesse tempo, o app manda um ping para a conexão não ser dada como morta.
const PING_EVERY_MS = 25_000;

type Handler = (data: unknown) => void;

/** Se o canal está escutando agora. O chat usa para decidir se precisa buscar a resposta sozinho. */
export const useRealtimeStatus = create<{ connected: boolean }>(() => ({ connected: false }));

/**
 * A conexão com o Reverb, uma só para o app inteiro. Conecta, autoriza o canal privado da pessoa
 * com o token da API, responde aos pings e reconecta sozinha quando cai. Quem quer ouvir um evento
 * usa on("assistant.delta", …).
 */
class Realtime {
  private socket: WebSocket | null = null;
  private channel: string | null = null;
  private wanted = false;
  private attempt = 0;
  private reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  private pingTimer: ReturnType<typeof setInterval> | null = null;
  private handlers = new Map<string, Set<Handler>>();

  constructor() {
    // Voltando para o app, reconecta na hora em vez de esperar a próxima tentativa.
    AppState.addEventListener('change', (state) => {
      if (state === 'active' && this.wanted && this.socket === null) {
        if (this.reconnectTimer !== null) {
          clearTimeout(this.reconnectTimer);
          this.reconnectTimer = null;
        }

        this.attempt = 0;
        this.open();
      }
    });
  }

  start(userId: number): void {
    this.channel = `private-user.${userId}`;
    this.wanted = true;
    this.open();
  }

  stop(): void {
    this.wanted = false;
    this.clearTimers();
    this.socket?.close();
    this.socket = null;
    useRealtimeStatus.setState({ connected: false });
  }

  /** Escuta um evento ("assistant.delta"). Devolve a função que para de escutar. */
  on(event: string, handler: Handler): () => void {
    const set = this.handlers.get(event) ?? new Set<Handler>();
    set.add(handler);
    this.handlers.set(event, set);

    return () => {
      set.delete(handler);
    };
  }

  private open(): void {
    if (!this.wanted || this.socket !== null || this.reconnectTimer !== null) {
      return;
    }

    const socket = new WebSocket(socketUrl(URL, KEY));
    this.socket = socket;

    socket.onmessage = (message) => {
      this.receive(String(message.data));
    };

    socket.onclose = () => {
      if (this.socket === socket) {
        this.socket = null;
      }

      this.clearTimers();
      useRealtimeStatus.setState({ connected: false });

      if (this.wanted) {
        this.reconnectTimer = setTimeout(() => {
          this.reconnectTimer = null;
          this.open();
        }, reconnectDelay(this.attempt++));
      }
    };
  }

  private receive(raw: string): void {
    const frame = parseFrame(raw);

    if (frame === null) {
      return;
    }

    switch (frame.event) {
      case 'pusher:connection_established':
        this.attempt = 0;
        this.subscribe((frame.data as { socket_id: string }).socket_id);
        this.pingTimer = setInterval(() => this.socket?.send(pingFrame()), PING_EVERY_MS);
        break;
      case 'pusher:ping':
        this.socket?.send(pongFrame());
        break;
      case 'pusher_internal:subscription_succeeded':
        useRealtimeStatus.setState({ connected: true });
        break;
      default:
        if (frame.channel === this.channel) {
          this.handlers.get(frame.event)?.forEach((handler) => handler(frame.data));
        }
    }
  }

  /** O backend assina o pedido (só a própria pessoa entra no canal dela) e o app manda para o Reverb. */
  private async subscribe(socketId: string): Promise<void> {
    try {
      const { auth } = await api<{ auth: string }>('/broadcasting/auth', {
        method: 'POST',
        body: { socket_id: socketId, channel_name: this.channel },
      });

      this.socket?.send(subscribeFrame(this.channel ?? '', auth));
    } catch {
      // Sem autorização (sem internet, por exemplo): fecha e a reconexão tenta de novo.
      this.socket?.close();
    }
  }

  private clearTimers(): void {
    if (this.pingTimer !== null) {
      clearInterval(this.pingTimer);
      this.pingTimer = null;
    }

    if (this.reconnectTimer !== null && !this.wanted) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
  }
}

export const realtime = new Realtime();

/** Mantém a conexão aberta enquanto a pessoa está logada (vai no layout do app). */
export function useRealtimeConnection(userId: number | undefined): void {
  useEffect(() => {
    if (userId === undefined) {
      return;
    }

    realtime.start(userId);

    return () => realtime.stop();
  }, [userId]);
}
