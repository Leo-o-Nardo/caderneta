import { useMutation } from '@tanstack/react-query';
import { addDays, format } from 'date-fns';
import { router } from 'expo-router';
import { Bell, Check } from 'lucide-react-native';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';

import { invalidateFinance } from '@/api/keys';
import { completeReminder, snoozeReminder, useReminders } from '@/api/reminders';
import type { Reminder } from '@/api/types';
import { Button, EmptyState, SectionHeader, Tag, Text } from '@/components/ui';
import { recurrenceLabel, reminderWhen } from '@/lib/reminders';
import { colors, space } from '@/theme/tokens';

/** Os lembretes ativos: atrasados no topo, depois os próximos, com "Feito" e "Adiar". */
export function RemindersSection() {
  const reminders = useReminders();
  const complete = useMutation({ mutationFn: completeReminder, onSuccess: () => invalidateFinance() });
  const snooze = useMutation({
    mutationFn: ({ id, until }: { id: number; until?: string }) => snoozeReminder(id, until),
    onSuccess: () => invalidateFinance(),
  });

  if (reminders.isPending) {
    return <ActivityIndicator color={colors.accent} style={styles.status} />;
  }

  if (reminders.isError) {
    return (
      <Text tone="danger" style={styles.status}>
        {reminders.error.message}
      </Text>
    );
  }

  if (reminders.data.length === 0) {
    return (
      <EmptyState
        icon={Bell}
        title="Nenhum lembrete"
        description="IPVA, vacina do pet, trocar o filtro da água… o app avisa no dia."
        action={<Button title="Criar lembrete" onPress={() => router.push('/reminders/new')} />}
      />
    );
  }

  // Adiar não apaga nada: a escolha fica no alerta do sistema (a folha do app é para confirmar o que não volta).
  const askSnooze = (reminder: Reminder) =>
    Alert.alert('Adiar para quando?', reminder.text, [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Em 1 semana', onPress: () => snooze.mutate({ id: reminder.id, until: format(addDays(new Date(), 7), 'yyyy-MM-dd') }) },
      // Sem data, a API adia 24 horas: amanhã, no mesmo horário.
      { text: 'Amanhã', onPress: () => snooze.mutate({ id: reminder.id }) },
    ]);

  const overdue = reminders.data.filter((reminder) => reminder.is_overdue);
  const next = reminders.data.filter((reminder) => !reminder.is_overdue);

  return (
    <View>
      {[
        { title: 'Atrasados', items: overdue },
        { title: 'Próximos', items: next },
      ]
        .filter((group) => group.items.length > 0)
        .map((group, index) => (
          <View key={group.title}>
            <SectionHeader title={group.title} detail={String(group.items.length)} style={index === 0 ? styles.firstHeader : undefined} />
            {group.items.map((reminder) => (
              <ReminderRow
                key={reminder.id}
                reminder={reminder}
                completing={complete.isPending && complete.variables === reminder.id}
                onDone={() => complete.mutate(reminder.id)}
                onSnooze={() => askSnooze(reminder)}
              />
            ))}
          </View>
        ))}
    </View>
  );
}

type RowProps = {
  reminder: Reminder;
  completing: boolean;
  onDone: () => void;
  onSnooze: () => void;
};

/** Um lembrete: o sino, o texto, quando toca e a repetição, "da casa" se for da casa. Toque abre a edição. */
function ReminderRow({ reminder, completing, onDone, onSnooze }: RowProps) {
  const details = [reminderWhen(reminder.remind_at), recurrenceLabel(reminder.recurrence, reminder.every)]
    .filter(Boolean)
    .join(' · ');

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/reminders/[id]', params: { id: String(reminder.id) } })}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole="button"
    >
      <View style={styles.iconColumn}>
        <Bell size={20} color={reminder.is_overdue ? colors.danger : colors.muted} strokeWidth={1.6} />
      </View>
      <View style={styles.texts}>
        <View style={styles.line}>
          <Text variant="strong" style={styles.shrink}>
            {reminder.text}
          </Text>
          {reminder.visibility === 'household' ? <Tag label="da casa" /> : null}
        </View>
        <Text variant="caption" tone={reminder.is_overdue ? 'danger' : 'muted'}>
          {details}
        </Text>
        <View style={styles.actions}>
          <Button title="Adiar" variant="secondary" size="sm" onPress={onSnooze} />
          <Button
            title="Feito"
            size="sm"
            icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
            loading={completing}
            onPress={onDone}
          />
        </View>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  firstHeader: { marginTop: space.sm },
  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: space.md,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  pressed: { backgroundColor: colors.pressed },
  iconColumn: { width: 24, alignItems: 'center' },
  texts: { flex: 1, gap: space.xs },
  line: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  shrink: { flexShrink: 1 },
  actions: { flexDirection: 'row', gap: space.sm, marginTop: space.xs },
});
