import { ChevronLeft, ChevronRight } from 'lucide-react-native';
import { Pressable, StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui';
import { monthLabel, monthTitle, shiftMonth } from '@/lib/dates';
import { colors, radius, space } from '@/theme/tokens';

type Props = {
  // "2026-10"
  month: string;
  onChange: (month: string) => void;
};

/** O ano em cima, o mês em Fraunces e as setas finas dos lados: troca o mês exibido. */
export function MonthSwitcher({ month, onChange }: Props) {
  return (
    <View style={styles.container}>
      <Pressable
        onPress={() => onChange(shiftMonth(month, -1))}
        style={({ pressed }) => [styles.arrow, pressed && styles.pressed]}
        accessibilityRole="button"
        accessibilityLabel="Mês anterior"
      >
        <ChevronLeft size={22} color={colors.muted} strokeWidth={1.6} />
      </Pressable>
      <View style={styles.title} accessible accessibilityRole="header" accessibilityLabel={monthLabel(month)}>
        <Text variant="overline" tone="muted">
          {month.slice(0, 4)}
        </Text>
        <Text variant="title">{monthTitle(month)}</Text>
      </View>
      <Pressable
        onPress={() => onChange(shiftMonth(month, 1))}
        style={({ pressed }) => [styles.arrow, pressed && styles.pressed]}
        accessibilityRole="button"
        accessibilityLabel="Próximo mês"
      >
        <ChevronRight size={22} color={colors.muted} strokeWidth={1.6} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flexDirection: 'row', alignItems: 'center', paddingTop: space.sm },
  arrow: { width: 44, height: 44, borderRadius: radius.md, alignItems: 'center', justifyContent: 'center' },
  pressed: { backgroundColor: colors.pressed },
  title: { flex: 1, alignItems: 'center' },
});
