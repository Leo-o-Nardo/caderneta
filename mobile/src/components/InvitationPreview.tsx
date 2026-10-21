import { useMutation } from '@tanstack/react-query';
import { House } from 'lucide-react-native';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { acceptInvitation, resetAfterHouseholdChange, useInvitationPreview } from '@/api/households';
import { Button, Form, Text } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { shortDate } from '@/lib/dates';
import { ROLES } from '@/lib/households';
import { colors, space } from '@/theme/tokens';

type Props = {
  code: string;
  // Depois de aceitar (a API já troca para a casa nova): cada tela decide para onde voltar.
  onAccepted: () => void;
  onCancel: () => void;
};

/**
 * O convite antes de aceitar: a casa, quem convidou, quantas pessoas já estão nela e o papel que a
 * pessoa vai ter, direto no papel. Usado pelo link do convite e pelo "Entrar com um código".
 */
export function InvitationPreview({ code, onAccepted, onCancel }: Props) {
  const preview = useInvitationPreview(code);
  const accept = useMutation({
    mutationFn: () => acceptInvitation(code),
    onSuccess: () => {
      onAccepted();
      // Tudo o que estava na tela era da casa anterior.
      resetAfterHouseholdChange();
    },
  });

  if (preview.isPending) {
    return <ActivityIndicator color={colors.accent} style={styles.status} />;
  }

  const household = preview.data?.household;

  if (preview.isError || !household) {
    // 404: o código não existe, já foi usado, expirou ou foi cancelado. A API não diz qual.
    const notFound = preview.error instanceof ApiError && preview.error.status === 404;

    return (
      <View style={styles.status}>
        <Text variant="subtitle">{notFound ? 'Convite inválido' : 'Não deu para abrir o convite'}</Text>
        <Text tone="muted">
          {notFound
            ? 'Este código não existe, já foi usado ou expirou. Peça um convite novo para quem te chamou.'
            : preview.error?.message}
        </Text>
        <Button title="Voltar" variant="secondary" onPress={onCancel} style={styles.back} />
      </View>
    );
  }

  const invitation = preview.data;
  const people = household.members_count === 1 ? '1 pessoa' : `${household.members_count} pessoas`;

  return (
    <Form
      error={accept.error?.message}
      submit={
        <>
          <Button title="Entrar na casa" loading={accept.isPending} onPress={() => accept.mutate()} />
          <Button title="Agora não" variant="secondary" onPress={onCancel} />
        </>
      }
    >
      <View style={styles.household}>
        <House size={24} color={colors.muted} strokeWidth={1.6} />
        <Text variant="caption" tone="muted">
          {invitation.invited_by} te convidou para
        </Text>
        <Text variant="title">{household.name}</Text>
        <Text variant="caption" tone="muted">
          {people} · vale até {shortDate(invitation.expires_at)}
        </Text>
      </View>

      {/* O papel, entre duas pautas: é o que muda o que a pessoa vai poder fazer. */}
      <View style={styles.role}>
        <Text variant="strong">Você entra como {ROLES[invitation.role].label.toLowerCase()}</Text>
        <Text variant="caption" tone="muted">
          {ROLES[invitation.role].description}
        </Text>
      </View>

      <Text variant="caption" tone="muted">
        Você continua nas suas outras casas e pode trocar entre elas nas Configurações.
      </Text>
    </Form>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl, gap: space.sm },
  back: { marginTop: space.lg },
  household: { gap: space.xs, paddingTop: space.lg },
  role: {
    gap: space.xs,
    paddingVertical: space.md,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: colors.line,
  },
});
