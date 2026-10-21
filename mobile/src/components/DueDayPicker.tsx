import { Pressable, StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui';
import { colors, radius, space } from '@/theme/tokens';

type Props = {
  value: number;
  onChange: (day: number) => void;
};

const DAYS = Array.from({ length: 31 }, (_, index) => index + 1);

/** Os dias 1 a 31 numa grade, para escolher o dia do vencimento com um toque. */
export function DueDayPicker({ value, onChange }: Props) {
  return (
    <View style={styles.grid}>
      {DAYS.map((day) => {
        const selected = day === value;

        return (
          <Pressable
            key={day}
            onPress={() => onChange(day)}
            style={[styles.day, selected && styles.selected]}
            accessibilityRole="button"
            accessibilityState={{ selected }}
            accessibilityLabel={`Dia ${day}`}
          >
            <Text variant="label" tone={selected ? 'onAccent' : 'ink'} style={styles.number}>
              {day}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
  // Quadrados de 40 com o contorno da Borda de campo; o dia escolhido fica preenchido de Musgo.
  day: {
    width: 40,
    height: 40,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  selected: { backgroundColor: colors.accent, borderColor: colors.accent },
  number: { fontVariant: ['tabular-nums'] },
});
