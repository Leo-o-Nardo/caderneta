import { useMutation } from '@tanstack/react-query';
import { format, parseISO } from 'date-fns';
import { router, useLocalSearchParams } from 'expo-router';
import { Check, UserMinus } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { changeMemberRole, invalidateHousehold, removeMember, useCurrentHousehold } from '@/api/households';
import type { HouseholdRole, Member } from '@/api/types';
import { Button, Field, Form, Header, Monogram, Notice, Screen, SegmentedControl, Text } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { ROLES } from '@/lib/households';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

const ROLE_OPTIONS: HouseholdRole[] = ['owner', 'member', 'viewer'];

/** O dono mudando o papel de alguém ou tirando a pessoa da casa. */
export default function MemberScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const household = useCurrentHousehold();
  const member = household.data?.members.find((item) => item.id === Number(id));

  return (
    <Screen>
      <Header title="Membro" leading="close" />

      {household.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : member ? (
        <MemberForm member={member} householdName={household.data?.name ?? ''} />
      ) : (
        <Text tone="muted" style={styles.status}>
          Esta pessoa não faz mais parte da casa.
        </Text>
      )}
    </Screen>
  );
}

function MemberForm({ member, householdName }: { member: Member; householdName: string }) {
  const [role, setRole] = useState<HouseholdRole>(member.role);

  const done = () => {
    router.back();
    invalidateHousehold();
  };

  const save = useMutation({ mutationFn: () => changeMemberRole(member.id, role), onSuccess: done });
  const remove = useMutation({ mutationFn: () => removeMember(member.id), onSuccess: done });

  const failure = save.error ?? remove.error;
  const error = failure instanceof ApiError ? failure : null;

  async function confirmRemove() {
    const confirmed = await confirm({
      title: `Tirar ${member.name} da casa?`,
      message: `${member.name} deixa de ver tudo de ${householdName}. O que já foi lançado continua na casa. Para voltar, precisa de um convite novo.`,
      confirmLabel: 'Tirar da casa',
      destructive: true,
    });

    if (confirmed) {
      remove.mutate();
    }
  }

  return (
    <Form
      error={error?.messageOutside(['role'])}
      submit={
        <Button
          title="Salvar"
          icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={save.isPending}
          disabled={role === member.role}
          onPress={() => save.mutate()}
        />
      }
    >
      <View style={styles.person}>
        <Monogram name={member.name} />
        <View style={styles.texts}>
          <Text variant="subtitle">{member.name}</Text>
          <Text variant="caption" tone="muted">
            {member.email} · na casa desde {format(parseISO(member.joined_at), 'dd/MM/yyyy')}
          </Text>
        </View>
      </View>

      <Field label="Papel" error={error?.field('role')} hint={ROLES[role].description}>
        <SegmentedControl
          options={ROLE_OPTIONS.map((value) => ({ value, label: ROLES[value].label }))}
          value={role}
          onChange={setRole}
        />
      </Field>

      {role === 'owner' && member.role !== 'owner' ? (
        <Notice>Como dono, {member.name} também vai poder convidar, mudar papéis e apagar a casa.</Notice>
      ) : null}

      <Button
        title="Tirar da casa"
        variant="dangerLink"
        icon={<UserMinus size={18} color={colors.danger} strokeWidth={1.6} />}
        loading={remove.isPending}
        onPress={confirmRemove}
      />
    </Form>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  person: { flexDirection: 'row', alignItems: 'center', gap: space.lg, paddingTop: space.sm },
  texts: { flex: 1, gap: space.xs },
});
