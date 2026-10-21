import { useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

import { ME_KEY } from './auth';
import { keys } from './keys';
import type { Household, HouseholdRole, Invitation, Member } from './types';

/** As casas de que a pessoa faz parte, com o papel em cada uma. */
export function useHouseholds() {
  return useQuery({ queryKey: keys.households, queryFn: () => api<Household[]>('/households') });
}

/** A casa atual, com os membros. */
export function useCurrentHousehold() {
  return useQuery({
    queryKey: keys.currentHousehold,
    queryFn: () => api<Household & { members: Member[] }>('/household'),
  });
}

/** Os convites pendentes da casa atual (só o dono vê). */
export function useInvitations(enabled: boolean) {
  return useQuery({
    queryKey: keys.invitations,
    queryFn: () => api<Invitation[]>('/household/invitations'),
    enabled,
  });
}

/** O convite antes de aceitar: casa, quem convidou e o papel. */
export function useInvitationPreview(code: string) {
  return useQuery({
    queryKey: keys.invitation(code),
    queryFn: () => api<Invitation>(`/invitations/${code}`),
    // Um 404 aqui é resposta (convite inválido), não falha passageira.
    retry: false,
  });
}

/** Cria a casa e já entra nela: a pessoa vira dona e a casa nova vira a atual. */
export function createHousehold(name: string): Promise<Household> {
  return api('/households', { method: 'POST', body: { name } });
}

export function switchHousehold(householdId: number): Promise<Household> {
  return api('/households/current', { method: 'PUT', body: { household_id: householdId } });
}

export function renameHousehold(name: string): Promise<Household> {
  return api('/household', { method: 'PATCH', body: { name } });
}

export function deleteHousehold(): Promise<void> {
  return api('/household', { method: 'DELETE' });
}

export function leaveHousehold(): Promise<void> {
  return api('/household/leave', { method: 'POST' });
}

export function changeMemberRole(memberId: number, role: HouseholdRole): Promise<Member> {
  return api(`/household/members/${memberId}`, { method: 'PATCH', body: { role } });
}

export function removeMember(memberId: number): Promise<void> {
  return api(`/household/members/${memberId}`, { method: 'DELETE' });
}

/** Nunca "owner": a API recusa, para um link vazado não entregar o controle da casa. */
export function createInvitation(role: Exclude<HouseholdRole, 'owner'>): Promise<Invitation> {
  return api('/household/invitations', { method: 'POST', body: { role } });
}

export function revokeInvitation(id: number): Promise<void> {
  return api(`/household/invitations/${id}`, { method: 'DELETE' });
}

/** Entra na casa do convite. A API já troca a casa atual para ela. */
export function acceptInvitation(code: string): Promise<Household> {
  return api(`/invitations/${code}/accept`, { method: 'POST' });
}

/**
 * Depois de trocar de casa (criar, trocar, sair, apagar, aceitar convite): todos os dados em cache
 * eram da casa anterior. Zera o cache e busca de novo o que estiver na tela.
 */
export function resetAfterHouseholdChange(): Promise<void> {
  return queryClient.resetQueries();
}

/** Depois de mudar membros, papéis, nome ou convites da casa atual. O /me tem o nome da casa. */
export async function invalidateHousehold(): Promise<void> {
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: keys.households }),
    queryClient.invalidateQueries({ queryKey: ME_KEY }),
  ]);
}
