import { useMutation } from '@tanstack/react-query';
import { addDays, addHours, format } from 'date-fns';
import { router } from 'expo-router';
import { Bell, Check, ChevronRight } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { useCanWrite } from '@/api/auth';
import { invalidateFinance } from '@/api/keys';
import { completeReminder, snoozeReminder, useReminders } from '@/api/reminders';
import type { Reminder } from '@/api/types';
import { BottomSheet, Button, EmptyState, SectionHeader, Tag, Text } from '@/components/ui';
import { shortDate } from '@/lib/dates';
import { recurrenceLabel, reminderWhen } from '@/lib/reminders';
import { colors, space } from '@/theme/tokens';

type SnoozeOption = {
  label: string;
  // Embaixo do nome, quando o lembrete volta a tocar.
  caption: string;
  // Sem "until", a API adia 24 horas: amanhã, no mesmo horário. Só a data vira 9h.
  until?: string;
};

/** As escolhas de "Adiar", as mesmas do aviso do lembrete no Chat ("Adiar 1 h", "Amanhã") e mais uma semana. */
function snoozeOptions(now: Date): SnoozeOption[] {
  const inAnHour = addHours(now, 1);
  const inAWeek = addDays(now, 7);

  return [
    { label: 'Em 1 hora', caption: `às ${format(inAnHour, 'HH:mm')}`, until: format(inAnHour, 'yyyy-MM-dd HH:mm') },
    { label: 'Amanhã', caption: 'no mesmo horário' },
    { label: 'Em 1 semana', caption: `${shortDate(format(inAWeek, 'yyyy-MM-dd'))}, às 9h`, until: format(inAWeek, 'yyyy-MM-dd') },
  ];
}

/**
 * Os lembretes ativos: atrasados no topo, depois os próximos, com "Feito" e "Adiar". "Adiar" abre a
 * folha do app com quando ele volta a tocar. Para quem é "Só leitura", a lista é só para ver.
 */
export function RemindersSection() {
  const reminders = useReminders();
  const canWrite = useCanWrite();
  // O lembrete cuja folha de "Adiar" está aberta.
  const [snoozing, setSnoozing] = useState<Reminder | null>(null);
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
        action={canWrite ? <Button title="Criar lembrete" onPress={() => router.push('/reminders/new')} /> : undefined}
      />
    );
  }

  function snoozeTo(option: SnoozeOption) {
    if (snoozing) {
      snooze.mutate({ id: snoozing.id, until: option.until });
    }

    setSnoozing(null);
  }

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
                canWrite={canWrite}
                completing={complete.isPending && complete.variables === reminder.id}
                onDone={() => complete.mutate(reminder.id)}
                onSnooze={() => setSnoozing(reminder)}
              />
            ))}
          </View>
        ))}

      {/* Adiar não apaga nada: a escolha é uma folha com as opções, e tocar fora cancela. */}
      <BottomSheet visible={snoozing !== null} onClose={() => setSnoozing(null)} closeLabel="Cancelar">
        {snoozing ? (
          <>
            <Text variant="subtitle" accessibilityRole="header">
              Adiar para quando?
            </Text>
            <Text tone="muted" style={styles.sheetNote} numberOfLines={2}>
              {snoozing.text}
            </Text>
            <View style={styles.options}>
              {snoozeOptions(new Date()).map((option) => (
                <Pressable
                  key={option.label}
                  onPress={() => snoozeTo(option)}
                  style={({ pressed }) => [styles.option, pressed && styles.pressed]}
                  accessibilityRole="button"
                  accessibilityLabel={`${option.label}, ${option.caption}`}
                >
                  <View style={styles.texts}>
                    <Text variant="strong">{option.label}</Text>
                    <Text variant="caption" tone="muted">
                      {option.caption}
                    </Text>
                  </View>
                  <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
                </Pressable>
              ))}
            </View>
          </>
        ) : null}
      </BottomSheet>
    </View>
  );
}

type RowProps = {
  reminder: Reminder;
  canWrite: boolean;
  completing: boolean;
  onDone: () => void;
  onSnooze: () => void;
};

/**
 * Um lembrete: o sino, o texto, quando toca e a repetição, "da casa" se for da casa. Toque abre a
 * edição; "Só leitura" vê a linha sem os botões e sem abrir.
 */
function ReminderRow({ reminder, canWrite, completing, onDone, onSnooze }: RowProps) {
  const details = [reminderWhen(reminder.remind_at), recurrenceLabel(reminder.recurrence, reminder.every)]
    .filter(Boolean)
    .join(' · ');

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/reminders/[id]', params: { id: String(reminder.id) } })}
      disabled={!canWrite}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      accessibilityRole={canWrite ? 'button' : undefined}
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
        {canWrite ? (
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
        ) : null}
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
  // A folha de "Adiar", no mesmo formato da folha "Convidar como?".
  sheetNote: { marginTop: space.sm },
  options: { marginTop: space.md, borderTopWidth: 1, borderTopColor: colors.line },
  option: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
});
