/**
 * As peças do protocolo Pusher, que o Reverb fala: tudo é JSON, e o "data" de cada evento chega
 * como texto JSON dentro do JSON. Aqui só montar e ler os quadros, sem conexão, para dar para testar.
 */

export type PusherFrame = {
  event: string;
  channel?: string;
  data: unknown;
};

/** Lê um quadro recebido. Quadro estragado vira null (é ignorado, não derruba a conexão). */
export function parseFrame(raw: string): PusherFrame | null {
  try {
    const frame = JSON.parse(raw) as { event?: unknown; channel?: unknown; data?: unknown };

    if (typeof frame !== 'object' || frame === null || typeof frame.event !== 'string') {
      return null;
    }

    let data = frame.data;

    if (typeof data === 'string') {
      try {
        data = JSON.parse(data);
      } catch {
        // Alguns eventos mandam texto puro: fica como veio.
      }
    }

    return { event: frame.event, channel: typeof frame.channel === 'string' ? frame.channel : undefined, data };
  } catch {
    return null;
  }
}

/** O endereço do WebSocket: o app (pela chave) e a versão do protocolo. */
export function socketUrl(base: string, key: string): string {
  return `${base.replace(/\/$/, '')}/app/${key}?protocol=7&client=js&version=8.4.0&flash=false`;
}

/** Pede para escutar um canal privado, com a assinatura que o backend deu. */
export function subscribeFrame(channel: string, auth: string): string {
  return JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth } });
}

export function pingFrame(): string {
  return JSON.stringify({ event: 'pusher:ping', data: {} });
}

export function pongFrame(): string {
  return JSON.stringify({ event: 'pusher:pong', data: {} });
}

/**
 * Quanto esperar antes de tentar reconectar: 1 s, 2 s, 5 s, 10 s e depois de 15 em 15 s. Rápido
 * para quedas curtas (túnel, troca de rede), sem martelar o servidor se ele estiver fora do ar.
 */
export function reconnectDelay(attempt: number): number {
  return [1000, 2000, 5000, 10_000][attempt] ?? 15_000;
}
