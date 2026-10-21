import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { useReminders } from '@/api/reminders';
import { ReminderForm } from '@/components/ReminderForm';
import { Header, Screen, Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

/** Editar um lembrete ativo. Ele vem da lista que a Agenda já carregou (a API não tem rota de um só). */
export default function EditReminderScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const reminders = useReminders();
  const reminder = reminders.data?.find((item) => item.id === Number(id));

  return (
    <Screen>
      <Header title="Lembrete" leading="close" />

      {reminders.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : reminder ? (
        <ReminderForm reminder={reminder} />
      ) : (
        <Text tone="muted" style={styles.status}>
          Este lembrete não está mais entre os ativos.
        </Text>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
