import type { HouseholdRole, Invitation } from '@/api/types';

import { shortDate } from './dates';

/** O nome e o que cada papel pode fazer na casa. */
export const ROLES: Record<HouseholdRole, { label: string; description: string }> = {
  owner: { label: 'Dono', description: 'Tudo, inclusive convidar, mudar papéis e apagar a casa.' },
  member: { label: 'Membro', description: 'Lança, edita e vê tudo o que é da casa.' },
  viewer: { label: 'Só leitura', description: 'Só vê. Não lança nem edita nada.' },
};

/**
 * Limpa o que foi digitado ou colado no campo do código: " abc-123 " → "ABC123". Se a pessoa colar
 * o link inteiro do convite, pega só o código do fim dele.
 */
export function normalizeInviteCode(text: string): string {
  const upper = text.toUpperCase();
  const fromLink = upper.match(/CONVITE\/([A-Z0-9]+)/)?.[1];

  return (fromLink ?? upper).replace(/[^A-Z0-9]/g, '').slice(0, 6);
}

/**
 * A mensagem que vai pelo WhatsApp (ou onde a pessoa escolher). O código vem escrito porque o link
 * só abre o app direto quando ele estiver publicado com o domínio próprio.
 */
export function inviteMessage(invitation: Invitation, householdName: string): string {
  return [
    `${invitation.invited_by} te convidou para a casa "${householdName}" na Caderneta, como ${ROLES[invitation.role].label.toLowerCase()}.`,
    `Código do convite: ${invitation.code}`,
    'Para entrar, crie sua conta no app com esse código. Se já tem conta, vá em Configurações › Entrar com um código.',
    `${invitation.url}`,
    `O convite vale até ${shortDate(invitation.expires_at)}.`,
  ].join('\n\n');
}

/** As iniciais para o círculo do membro: "Ana Souza" → "AS", "Leo" → "L". */
export function initials(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);
  const letters = words.length > 1 ? [words[0], words[words.length - 1]] : words;

  return letters.map((word) => word.charAt(0).toUpperCase()).join('');
}
