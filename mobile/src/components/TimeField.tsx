import DateTimePicker, { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { format } from 'date-fns';
import { Clock } from 'lucide-react-native';
import { useState } from 'react';
import { Platform, StyleSheet, View } from 'react-native';

import { Chip, Field } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

type Props = {
  label?: string;
  // "09:00"
  value: string;
  onChange: (time: string) => void;
};

// Os horários mais usados num toque; os outros, pelo relógio do sistema.
const QUICK_TIMES = ['09:00', '12:00', '18:00'];

export function TimeField({ label = 'Horário', value, onChange }: Props) {
  const [showIosPicker, setShowIosPicker] = useState(false);
  const isOther = !QUICK_TIMES.includes(value);

  function asDate(): Date {
    const [hours, minutes] = value.split(':').map(Number);
    const date = new Date();
    date.setHours(hours, minutes, 0, 0);

    return date;
  }

  function pickTime() {
    if (Platform.OS === 'android') {
      DateTimePickerAndroid.open({
        value: asDate(),
        mode: 'time',
        is24Hour: true,
        onChange: (event, selected) => {
          if (event.type === 'set' && selected) {
            onChange(format(selected, 'HH:mm'));
          }
        },
      });
    } else {
      setShowIosPicker(true);
    }
  }

  return (
    <Field label={label}>
      <View style={styles.wrap}>
        {QUICK_TIMES.map((time) => (
          <Chip key={time} label={time} selected={value === time} onPress={() => onChange(time)} />
        ))}
        <Chip
          label={isOther ? value : 'Outro…'}
          icon={<Clock size={18} color={isOther ? colors.accent : colors.muted} strokeWidth={1.6} />}
          selected={isOther}
          onPress={pickTime}
        />
      </View>
      {showIosPicker ? (
        <DateTimePicker
          value={asDate()}
          mode="time"
          display="spinner"
          themeVariant="light"
          onChange={(_, selected) => selected && onChange(format(selected, 'HH:mm'))}
        />
      ) : null}
    </Field>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
});
