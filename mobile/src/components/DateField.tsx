import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { addDays, parseISO } from 'date-fns';
import { CalendarDays } from 'lucide-react-native';
import { useState } from 'react';
import { Platform, StyleSheet, View } from 'react-native';

import { Chip, Field } from '@/components/ui';
import { shortDate, toISODate, todayISO } from '@/lib/dates';
import { colors, space } from '@/theme/tokens';

type Props = {
  label?: string;
  // "2026-10-07"
  value: string;
  onChange: (date: string) => void;
  // Lançamentos olham para trás ("Ontem"); lembretes, para a frente ("Amanhã").
  direction?: 'past' | 'future';
  error?: string;
};

/** "Hoje", "Ontem" (ou "Amanhã") e "Outra data…", que abre o seletor de data do sistema. */
export function DateField({ label = 'Data', value, onChange, direction = 'past', error }: Props) {
  const [showIosPicker, setShowIosPicker] = useState(false);
  const today = todayISO();
  const neighbor = toISODate(addDays(new Date(), direction === 'past' ? -1 : 1));
  const isOther = value !== today && value !== neighbor;

  function pickDate() {
    if (Platform.OS === 'android') {
      DateTimePickerAndroid.open({
        value: parseISO(value),
        mode: 'date',
        onChange: (event, selected) => {
          if (event.type === 'set' && selected) {
            onChange(toISODate(selected));
          }
        },
      });
    } else {
      setShowIosPicker(true);
    }
  }

  return (
    <Field label={label} error={error}>
      <View style={styles.wrap}>
        <Chip label="Hoje" selected={value === today} onPress={() => onChange(today)} />
        <Chip
          label={direction === 'past' ? 'Ontem' : 'Amanhã'}
          selected={value === neighbor}
          onPress={() => onChange(neighbor)}
        />
        <Chip
          label={isOther ? shortDate(value) : 'Outra data…'}
          icon={<CalendarDays size={18} color={isOther ? colors.accent : colors.muted} strokeWidth={1.6} />}
          selected={isOther}
          onPress={pickDate}
        />
      </View>
      {showIosPicker ? (
        <DateTimePicker
          value={parseISO(value)}
          mode="date"
          display="inline"
          themeVariant="light"
          onChange={(_, selected) => selected && onChange(toISODate(selected))}
        />
      ) : null}
    </Field>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
});
