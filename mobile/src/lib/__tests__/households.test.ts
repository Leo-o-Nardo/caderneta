import type { Invitation } from '@/api/types';

import { canWrite, initials, inviteMessage, normalizeInviteCode } from '../households';

describe('canWrite', () => {
  it('lets owners and members write', () => {
    expect(canWrite('owner')).toBe(true);
    expect(canWrite('member')).toBe(true);
  });

  it('keeps read-only people out', () => {
    expect(canWrite('viewer')).toBe(false);
  });

  it('shows the buttons while the role is still loading', () => {
    expect(canWrite(undefined)).toBe(true);
    expect(canWrite(null)).toBe(true);
  });
});

describe('normalizeInviteCode', () => {
  it('uppercases and drops spaces and symbols', () => {
    expect(normalizeInviteCode(' abc-123 ')).toBe('ABC123');
    expect(normalizeInviteCode('xk7p2m')).toBe('XK7P2M');
  });

  it('takes the code out of a pasted link', () => {
    expect(normalizeInviteCode('https://exemplo.com/convite/XK7P2M')).toBe('XK7P2M');
    expect(normalizeInviteCode('Entre pelo link: http://localhost:8100/convite/ab23cd')).toBe('AB23CD');
  });

  it('keeps at most 6 characters', () => {
    expect(normalizeInviteCode('ABCDEFGH')).toBe('ABCDEF');
  });
});

describe('inviteMessage', () => {
  const invitation: Invitation = {
    id: 1,
    code: 'XK7P2M',
    url: 'http://localhost:8100/convite/XK7P2M',
    role: 'viewer',
    invited_by: 'Leo',
    expires_at: '2026-10-15T12:00:00-03:00',
  };

  it('has who invited, the house, the role, the code, the link and the expiry', () => {
    const message = inviteMessage(invitation, 'Casa do Leo');

    expect(message).toContain('Leo te convidou para a casa "Casa do Leo" na Caderneta');
    expect(message).toContain('como só leitura');
    expect(message).toContain('Código do convite: XK7P2M');
    expect(message).toContain('http://localhost:8100/convite/XK7P2M');
    expect(message).toContain('vale até 15/10');
  });
});

describe('initials', () => {
  it('uses the first and the last name', () => {
    expect(initials('Ana Souza')).toBe('AS');
    expect(initials('Leonardo Ferreira de Souza')).toBe('LS');
    expect(initials(' leo ')).toBe('L');
  });
});
