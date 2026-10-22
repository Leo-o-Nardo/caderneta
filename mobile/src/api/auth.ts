import { queryOptions, useQuery } from '@tanstack/react-query';
import * as Device from 'expo-device';

import { api } from '@/lib/api';
import { canWrite } from '@/lib/households';
import { queryClient } from '@/lib/queryClient';
import { useSession } from '@/stores/session';

import type { AlertSettings, AuthResponse, User } from './types';

export const ME_KEY = ['me'] as const;

export type LoginInput = {
  email: string;
  password: string;
};

export type RegisterInput = {
  name: string;
  email: string;
  password: string;
  invite_code?: string;
};

export type ResetPasswordInput = {
  email: string;
  code: string;
  password: string;
};

export async function login(input: LoginInput): Promise<void> {
  const response = await api<AuthResponse>('/auth/login', {
    method: 'POST',
    body: { ...input, device_name: deviceName() },
    token: null,
  });

  await startSession(response);
}

/** O cadastro: entrou direto (por convite) ou espera a liberação, com a mensagem para mostrar. */
export type RegisterResult = { pending: false } | { pending: true; message: string };

type PendingResponse = { status: 'pending'; message: string };

/**
 * Por convite, a conta entra liberada e a sessão já começa. Sem convite, a API responde "em
 * análise" e não manda login: a pessoa recebe um e-mail quando o acesso for liberado.
 */
export async function register(input: RegisterInput): Promise<RegisterResult> {
  const response = await api<AuthResponse | PendingResponse>('/auth/register', {
    method: 'POST',
    body: { ...input, device_name: deviceName() },
    token: null,
  });

  if ('status' in response && response.status === 'pending') {
    return { pending: true, message: response.message };
  }

  await startSession(response as AuthResponse);

  return { pending: false };
}

/** Pede o código de 6 dígitos por e-mail. A API responde igual se o e-mail não existir. */
export function requestPasswordCode(email: string): Promise<void> {
  return api('/auth/forgot-password', { method: 'POST', body: { email }, token: null });
}

export function resetPassword(input: ResetPasswordInput): Promise<void> {
  return api('/auth/reset-password', { method: 'POST', body: input, token: null });
}

export type ChangePasswordInput = {
  current_password: string;
  password: string;
};

/** Troca o nome. A resposta já é o usuário atualizado: vai direto para o cache. */
export async function updateProfile(input: { name: string }): Promise<User> {
  const user = await api<User>('/me', { method: 'PATCH', body: input });
  queryClient.setQueryData(ME_KEY, user);

  return user;
}

/** Liga ou desliga tipos de aviso (só os que mudaram). O usuário devolvido vai direto para o cache. */
export async function updateAlertSettings(settings: Partial<AlertSettings>): Promise<User> {
  const user = await api<User>('/me', { method: 'PATCH', body: { alert_settings: settings } });
  queryClient.setQueryData(ME_KEY, user);

  return user;
}

/** Troca a senha. A API desconecta os outros aparelhos; este continua logado. */
export function changePassword(input: ChangePasswordInput): Promise<void> {
  return api('/me/password', { method: 'PUT', body: input });
}

/** Sai deste aparelho. Mesmo sem internet, a sessão local é encerrada. */
export async function signOut(): Promise<void> {
  await api('/auth/logout', { method: 'POST' }).catch(() => undefined);
  queryClient.clear();
  await useSession.getState().clear();
}

/**
 * Sai de todos os aparelhos (celular perdido, por exemplo). Ao contrário do signOut, se a chamada
 * falhar o erro sobe: dizer "saiu de tudo" sem a API confirmar seria enganar a pessoa.
 */
export async function signOutEverywhere(): Promise<void> {
  await api('/auth/logout-all', { method: 'POST' });
  queryClient.clear();
  await useSession.getState().clear();
}

/** O usuário logado, com a casa atual e o papel nela. */
export const meQuery = queryOptions({ queryKey: ME_KEY, queryFn: () => api<User>('/me') });

export function useMe() {
  return useQuery(meQuery);
}

/** Se a pessoa pode gravar na casa atual: quem é "Só leitura" não vê os botões de lançar e editar. */
export function useCanWrite(): boolean {
  const { data: me } = useMe();

  return canWrite(me?.current_household?.role);
}

/** O login e o cadastro já devolvem o usuário: ele vai direto para o cache, sem chamar o /me. */
async function startSession({ token, user }: AuthResponse): Promise<void> {
  queryClient.clear();
  queryClient.setQueryData(ME_KEY, user);
  await useSession.getState().signIn(token);
}

/** Vira o nome do token ("Pixel 8"): cada aparelho tem o próprio login. */
function deviceName(): string {
  return Device.deviceName ?? Device.modelName ?? 'Celular';
}
