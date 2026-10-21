import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { ChevronRight, LogOut, Pencil, Share2, Ticket, Trash2, UserPlus, X } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, Share, StyleSheet, View } from 'react-native';

import {
  createInvitation,
  deleteHousehold,
  invalidateHousehold,
  leaveHousehold,
  resetAfterHouseholdChange,
  revokeInvitation,
  useCurrentHousehold,
  useHouseholds,
  useInvitations,
} from '@/api/households';
import type { HouseholdRole, Invitation, Member } from '@/api/types';
import { BottomSheet, Button, Header, IconButton, Monogram, Screen, SectionHeader, Text } from '@/components/ui';
import { shortDate } from '@/lib/dates';
import { inviteMessage, ROLES } from '@/lib/households';
import { confirm } from '@/stores/confirm';
import { colors, font, space } from '@/theme/tokens';

// Os papéis de quem é convidado: dono só se vira depois, mudando o papel de quem já entrou.
type InviteRole = Exclude<HouseholdRole, 'owner'>;
const INVITE_ROLES: InviteRole[] = ['member', 'viewer'];

/**
 * A casa atual: quem faz parte e com qual papel, os convites que ainda não foram usados, e as
 * saídas (sair da casa ou, para o dono, apagar). Só o dono convida e mexe nos membros.
 */
export default function HouseholdScreen() {
  const household = useCurrentHousehold();
  const households = useHouseholds();
  const isOwner = household.data?.role === 'owner';
  const invitations = useInvitations(isOwner);
  // As duas folhas da tela: escolher o papel do convite e abrir um convite já feito.
  const [choosingRole, setChoosingRole] = useState(false);
  const [openInvitation, setOpenInvitation] = useState<Invitation | null>(null);

  // Depois de sair ou apagar, a pessoa está em outra casa: volta às Configurações e recarrega tudo.
  const leaveScreen = () => {
    router.back();
    resetAfterHouseholdChange();
  };

  const showError = (error: Error) => Alert.alert('Não deu certo', error.message);

  const share = (invitation: Invitation) => {
    Share.share({ message: inviteMessage(invitation, household.data?.name ?? '') }).catch(() => undefined);
  };

  const invite = useMutation({
    mutationFn: createInvitation,
    onSuccess: (invitation) => {
      invalidateHousehold();
      share(invitation);
    },
    onError: showError,
  });
  const revoke = useMutation({ mutationFn: revokeInvitation, onSuccess: () => invalidateHousehold(), onError: showError });
  const leave = useMutation({ mutationFn: leaveHousehold, onSuccess: leaveScreen, onError: showError });
  const destroy = useMutation({ mutationFn: deleteHousehold, onSuccess: leaveScreen, onError: showError });

  if (household.isPending) {
    return (
      <Screen>
        <Header title="Casa" />
        <ActivityIndicator color={colors.accent} style={styles.status} />
      </Screen>
    );
  }

  if (household.isError) {
    return (
      <Screen>
        <Header title="Casa" />
        <Text tone="danger" style={styles.status}>
          {household.error.message}
        </Text>
      </Screen>
    );
  }

  const { name, members } = household.data;
  const onlyHousehold = (households.data?.length ?? 1) <= 1;

  function inviteAs(role: InviteRole) {
    setChoosingRole(false);
    invite.mutate(role);
  }

  function cancelInvitation(invitation: Invitation) {
    setOpenInvitation(null);
    revoke.mutate(invitation.id);
  }

  async function confirmLeave() {
    const confirmed = await confirm({
      title: `Sair de ${name}?`,
      message:
        'Você deixa de ver os lançamentos, as contas e as listas desta casa. Para voltar, precisa de um convite novo.' +
        (onlyHousehold ? ' Como esta é sua única casa, uma casa nova e vazia é criada para você.' : ''),
      confirmLabel: 'Sair da casa',
      destructive: true,
    });

    if (confirmed) {
      leave.mutate();
    }
  }

  async function confirmDelete() {
    const confirmed = await confirm({
      title: `Apagar ${name}?`,
      message: 'Tudo some para todos os membros: lançamentos, cartões, contas, listas e lembretes. Não dá para desfazer.',
      confirmLabel: 'Apagar casa',
      destructive: true,
    });

    if (confirmed) {
      destroy.mutate();
    }
  }

  return (
    <Screen scroll>
      <Header
        title={name}
        right={
          isOwner ? (
            <IconButton
              icon={Pencil}
              label="Renomear casa"
              onPress={() => router.push({ pathname: '/settings/household-form', params: { mode: 'rename' } })}
            />
          ) : undefined
        }
      />

      <SectionHeader title="Membros" detail={String(members.length)} style={styles.firstHeader} />
      {members.map((member) => (
        <MemberRow
          key={member.id}
          member={member}
          // O dono mexe nos outros; o próprio papel ele muda passando a casa para outro dono.
          onPress={
            isOwner && !member.is_you
              ? () => router.push({ pathname: '/settings/member/[id]', params: { id: String(member.id) } })
              : undefined
          }
        />
      ))}

      {isOwner ? (
        <Button
          title="Convidar alguém"
          variant="secondary"
          icon={<UserPlus size={18} color={colors.ink} strokeWidth={1.6} />}
          loading={invite.isPending}
          onPress={() => setChoosingRole(true)}
          style={styles.invite}
        />
      ) : (
        <Text variant="caption" tone="muted" style={styles.note}>
          Só o dono da casa convida pessoas e muda os papéis.
        </Text>
      )}

      {isOwner && invitations.data && invitations.data.length > 0 ? (
        <>
          <SectionHeader title="Convites ainda não usados" detail={String(invitations.data.length)} />
          {invitations.data.map((invitation) => (
            <Pressable
              key={invitation.id}
              onPress={() => setOpenInvitation(invitation)}
              style={({ pressed }) => [styles.row, pressed && styles.pressed]}
              accessibilityRole="button"
              accessibilityLabel={`Convite ${invitation.code}, ${ROLES[invitation.role].label}, vale até ${shortDate(invitation.expires_at)}`}
            >
              <Ticket size={20} color={colors.muted} strokeWidth={1.6} />
              <View style={styles.texts}>
                <Text variant="strong" style={styles.code}>
                  {invitation.code}
                </Text>
                <Text variant="caption" tone="muted">
                  {ROLES[invitation.role].label} · vale até {shortDate(invitation.expires_at)}
                </Text>
              </View>
              <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
            </Pressable>
          ))}
          <Text variant="caption" tone="muted" style={styles.note}>
            Cada convite serve para uma pessoa. Toque para compartilhar de novo ou cancelar.
          </Text>
        </>
      ) : null}

      <View style={styles.footer}>
        <Button
          title="Sair desta casa"
          variant="dangerLink"
          icon={<LogOut size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={leave.isPending}
          onPress={confirmLeave}
        />
        {isOwner ? (
          <Button
            title="Apagar casa"
            variant="dangerLink"
            icon={<Trash2 size={18} color={colors.danger} strokeWidth={1.6} />}
            loading={destroy.isPending}
            onPress={confirmDelete}
          />
        ) : null}
      </View>

      {/* Convidar: o papel de quem vai entrar, com o que cada um pode fazer. */}
      <BottomSheet visible={choosingRole} onClose={() => setChoosingRole(false)} closeLabel="Cancelar">
        <Text variant="subtitle" accessibilityRole="header">
          Convidar como?
        </Text>
        <View style={styles.options}>
          {INVITE_ROLES.map((role) => (
            <Pressable
              key={role}
              onPress={() => inviteAs(role)}
              style={({ pressed }) => [styles.row, pressed && styles.pressed]}
              accessibilityRole="button"
              accessibilityLabel={`${ROLES[role].label}: ${ROLES[role].description}`}
            >
              <View style={styles.texts}>
                <Text variant="strong">{ROLES[role].label}</Text>
                <Text variant="caption" tone="muted">
                  {ROLES[role].description}
                </Text>
              </View>
              <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
            </Pressable>
          ))}
        </View>
        <Text variant="caption" tone="muted" style={styles.sheetNote}>
          O convite vale por 7 dias e serve para uma pessoa. Depois de escolher, é só compartilhar.
        </Text>
      </BottomSheet>

      {/* Um convite já feito: compartilhar de novo ou cancelar. */}
      <BottomSheet visible={openInvitation !== null} onClose={() => setOpenInvitation(null)}>
        {openInvitation ? (
          <>
            <Text variant="subtitle" accessibilityRole="header">
              Convite <Text variant="subtitle" style={styles.code}>{openInvitation.code}</Text>
            </Text>
            <Text tone="muted" style={styles.sheetNote}>
              {ROLES[openInvitation.role].label} · vale até {shortDate(openInvitation.expires_at)}. Quem usar o código entra na casa
              com esse papel.
            </Text>
            <View style={styles.sheetButtons}>
              <Button
                title="Compartilhar de novo"
                icon={<Share2 size={18} color={colors.canvas} strokeWidth={1.6} />}
                onPress={() => share(openInvitation)}
              />
              <Button
                title="Cancelar convite"
                variant="dangerLink"
                icon={<X size={18} color={colors.danger} strokeWidth={1.6} />}
                onPress={() => cancelInvitation(openInvitation)}
                style={styles.center}
              />
            </View>
          </>
        ) : null}
      </BottomSheet>
    </Screen>
  );
}

type MemberRowProps = {
  member: Member;
  // Sem onPress, a linha é só leitura (quem não é dono, ou a própria pessoa).
  onPress?: () => void;
};

function MemberRow({ member, onPress }: MemberRowProps) {
  return (
    <Pressable
      onPress={onPress}
      disabled={!onPress}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole={onPress ? 'button' : undefined}
    >
      <Monogram name={member.name} size="sm" />
      <View style={styles.texts}>
        <Text variant="strong" numberOfLines={1}>
          {member.name}
          {member.is_you ? (
            <Text variant="strong" tone="muted">
              {' (você)'}
            </Text>
          ) : null}
        </Text>
        <Text variant="caption" tone="muted" numberOfLines={1}>
          {member.email}
        </Text>
      </View>
      <Text variant="label" tone="muted">
        {ROLES[member.role].label}
      </Text>
      {onPress ? <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} /> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  firstHeader: { marginTop: space.md },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    minHeight: 56,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  pressed: { backgroundColor: colors.pressed },
  texts: { flex: 1, gap: space.xs },
  code: { fontFamily: font.semibold, letterSpacing: 2 },
  invite: { marginTop: space.lg },
  note: { marginTop: space.md },
  footer: { marginTop: space.xxl, gap: space.lg },
  options: { marginTop: space.md, borderTopWidth: 1, borderTopColor: colors.line },
  sheetNote: { marginTop: space.md },
  sheetButtons: { gap: space.lg, marginTop: space.xl },
  center: { alignSelf: 'center' },
});
