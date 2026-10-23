import { useSession } from '@/stores/session';

import { ApiError, OFFLINE_MESSAGE, toApiError } from './apiError';
import { queryClient } from './queryClient';

const BASE_URL = process.env.EXPO_PUBLIC_API_URL ?? 'http://10.0.2.2:8100/api/v1';

/** Uma página do servidor, fora da API ("/privacidade"): o mesmo endereço, sem o /api/v1. */
export function siteUrl(path: string): string {
  return BASE_URL.replace(/\/api\/v1\/?$/, '') + path;
}

type Options = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  // null = chamada sem login (entrar, cadastrar); sem informar, usa o token da sessão.
  token?: string | null;
};

/**
 * Chama a API e devolve o JSON da resposta. Qualquer falha vira um ApiError com a mensagem pronta.
 * Um 401 numa chamada com login significa token inválido: a sessão é encerrada e o app volta
 * para a tela de entrar.
 */
export async function api<T>(path: string, { method = 'GET', body, token }: Options = {}): Promise<T> {
  const authToken = token === undefined ? useSession.getState().token : token;
  // Arquivo (a gravação de voz) vai como FormData: o fetch monta o multipart e o cabeçalho sozinho.
  const isForm = body instanceof FormData;

  let response: Response;
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body !== undefined && !isForm ? { 'Content-Type': 'application/json' } : {}),
        ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
      },
      body: isForm ? (body as FormData) : body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (error) {
    // A pessoa vê "sem conexão"; em desenvolvimento, o erro original vai para o terminal do Expo.
    if (__DEV__) {
      console.warn(`[api] ${method} ${path} falhou antes da resposta:`, error);
    }

    throw new ApiError(0, OFFLINE_MESSAGE);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const data: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    if (response.status === 401 && authToken) {
      queryClient.clear();
      await useSession.getState().clear();
    }

    throw toApiError(response.status, data);
  }

  return data as T;
}
