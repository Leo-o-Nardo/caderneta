import { useMutation } from '@tanstack/react-query';
import { format, parseISO } from 'date-fns';
import { Check, Plus, Trash2 } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { createReminder, deleteReminder, updateReminder } from '@/api/reminders';
import type { Recurrence, Reminder } from '@/api/types';
import { Button, Chip, Field, Form, Stepper, TextField, ToggleRow } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { todayISO } from '@/lib/dates';
import { closeAndRefresh } from '@/lib/navigation';
import { recurrenceLabel, reminderWhen } from '@/lib/reminders';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

import { DateField } from './DateField';
import { TimeField } from './TimeField';

const RECURRENCES: { value: Recurrence; label: string; unit: string }[] = [
  { value: 'none', label: 'Não repete', unit: '' },
  { value: 'daily', label: 'Todo dia', unit: 'dias' },
  { value: 'weekly', label: 'Toda semana', unit: 'semanas' },
  { value: 'monthly', label: 'Todo mês', unit: 'meses' },
  { value: 'yearly', label: 'Todo ano', unit: 'anos' },
];

const VISIBLE_FIELDS = ['text', 'remind_at', 'recurrence', 'every', 'visibility'];

type Props = {
  // Sem lembrete: novo. Com lembrete: edição.
  reminder?: Reminder;
};

/** O texto, o dia e a hora, a repetição ("a cada 6 meses") e se é da casa ou só seu. */
export function ReminderForm({ reminder }: Props) {
  // A data vem com o fuso ("…-03:00") e é mostrada no horário do aparelho.
  const initial = reminder ? parseISO(reminder.remind_at) : null;

  const [text, setText] = useState(reminder?.text ?? '');
  const [date, setDate] = useState(initial ? format(initial, 'yyyy-MM-dd') : todayISO());
  const [time, setTime] = useState(initial ? format(initial, 'HH:mm') : '09:00');
  const [recurrence, setRecurrence] = useState<Recurrence>(reminder?.recurrence ?? 'none');
  const [every, setEvery] = useState(reminder?.every ?? 1);
  const [household, setHousehold] = useState(reminder?.visibility === 'household');

  const save = useMutation({
    mutationFn: (): Promise<unknown> => {
      const input = {
        text,
        remind_at: `${date} ${time}`,
        recurrence,
        every: recurrence === 'none' ? 1 : every,
        visibility: household ? ('household' as const) : ('private' as const),
      };

      return reminder ? updateReminder(reminder.id, input) : createReminder(input);
    },
    onSuccess: closeAndRefresh,
  });

  const remove = useMutation({ mutationFn: () => deleteReminder(reminder?.id ?? 0), onSuccess: closeAndRefresh });

  const failure = save.error ?? remove.error;
  const error = failure instanceof ApiError ? failure : null;
  const unit = RECURRENCES.find((option) => option.value === recurrence)?.unit ?? '';

  // A folha repete o lembrete e quando ele toca, para não apagar o errado.
  async function confirmDelete() {
    if (!reminder) {
      return;
    }

    const when = [reminderWhen(reminder.remind_at), recurrenceLabel(reminder.recurrence, reminder.every)].filter(Boolean).join(' · ');
    const confirmed = await confirm({
      title: 'Apagar o lembrete?',
      message: `${reminder.text} · ${when}. Ele sai da Agenda e não avisa mais.`,
      confirmLabel: 'Apagar lembrete',
      destructive: true,
    });

    if (confirmed) {
      remove.mutate();
    }
  }

  return (
    <Form
      error={error?.messageOutside(VISIBLE_FIELDS)}
      submit={
        <Button
          title={reminder ? 'Salvar' : 'Criar lembrete'}
          icon={
            reminder ? <Check size={18} color={colors.canvas} strokeWidth={1.6} /> : <Plus size={18} color={colors.canvas} strokeWidth={1.6} />
          }
          loading={save.isPending}
          onPress={() => save.mutate()}
        />
      }
    >
      <TextField
        label="Lembrete"
        value={text}
        onChangeText={setText}
        placeholder="Ex.: Pagar o IPVA"
        maxLength={160}
        autoFocus={!reminder}
        error={error?.field('text')}
      />

      <DateField label="Dia" value={date} onChange={setDate} direction="future" error={error?.field('remind_at')} />
      <TimeField value={time} onChange={setTime} />

      <Field label="Repetir" error={error?.field('recurrence') ?? error?.field('every')}>
        <View style={styles.wrap}>
          {RECURRENCES.map((option) => (
            <Chip key={option.value} label={option.label} selected={recurrence === option.value} onPress={() => setRecurrence(option.value)} />
          ))}
        </View>
        {recurrence !== 'none' ? (
          <Stepper value={every} onChange={setEvery} min={1} max={24} format={(value) => `a cada ${value}`} suffix={unit} label="intervalo" />
        ) : null}
      </Field>

      <ToggleRow
        label="Da casa"
        description="Todos da casa veem este lembrete. Desligado, só você vê."
        value={household}
        onChange={setHousehold}
      />

      {reminder ? (
        <Button
          title="Apagar lembrete"
          variant="dangerLink"
          icon={<Trash2 size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={remove.isPending}
          onPress={confirmDelete}
        />
      ) : null}
    </Form>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
});
