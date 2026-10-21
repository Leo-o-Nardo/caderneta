import { Minus, Plus } from 'lucide-react-native';
import { Pressable, StyleSheet, View } from 'react-native';

import { colors, radius, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  value: number;
  onChange: (value: number) => void;
  min: number;
  max: number;
  // O texto do número ("3×", "6"); por padrão, o próprio número.
  format?: (value: number) => string;
  // O que vem depois: "de R$ 250,00", "meses".
  suffix?: string;
  label: string;
};

/** "−  3×  +  de R$ 250,00": um número que sobe e desce dentro de um intervalo. */
export function Stepper({ value, onChange, min, max, format = String, suffix, label }: Props) {
  return (
    <View style={styles.row}>
      <Pressable
        onPress={() => onChange(Math.max(min, value - 1))}
        disabled={value <= min}
        style={({ pressed }) => [styles.button, pressed && styles.pressed, value <= min && styles.disabled]}
        hitSlop={4}
        accessibilityRole="button"
        accessibilityLabel={`Diminuir ${label}`}
      >
        <Minus size={18} color={colors.ink} strokeWidth={1.6} />
      </Pressable>
      <Text variant="amount" style={styles.value}>
        {format(value)}
      </Text>
      <Pressable
        onPress={() => onChange(Math.min(max, value + 1))}
        disabled={value >= max}
        style={({ pressed }) => [styles.button, pressed && styles.pressed, value >= max && styles.disabled]}
        hitSlop={4}
        accessibilityRole="button"
        accessibilityLabel={`Aumentar ${label}`}
      >
        <Plus size={18} color={colors.ink} strokeWidth={1.6} />
      </Pressable>
      {suffix ? <Text tone="muted">{suffix}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: space.md },
  button: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: { backgroundColor: colors.pressed },
  disabled: { opacity: 0.4 },
  value: { minWidth: 32, textAlign: 'center' },
});
