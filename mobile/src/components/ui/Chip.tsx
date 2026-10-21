import type { ReactNode } from 'react';
import { Pressable, StyleSheet } from 'react-native';

import { colors, radius, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  label: string;
  selected?: boolean;
  onPress?: () => void;
  disabled?: boolean;
  // O ícone pequeno antes do nome (o da categoria ou da lista, na cor dela).
  icon?: ReactNode;
  // Depois do nome, em Grafite: a contagem ("3") ou um complemento.
  detail?: string;
};

/** As tags de escolha do guia (categoria, forma de pagamento, lista): canto 4, escolhida em Musgo. */
export function Chip({ label, selected = false, onPress, disabled = false, icon, detail }: Props) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected, disabled }}
      onPress={onPress}
      disabled={disabled}
      // 36 de altura + 4 de cada lado = os 44 de área de toque do guia.
      hitSlop={4}
      style={({ pressed }) => [
        styles.chip,
        pressed && styles.pressed,
        selected && styles.selected,
        disabled && !selected && styles.disabled,
      ]}
    >
      {icon}
      <Text variant="label" tone={selected ? 'accent' : 'ink'}>
        {label}
      </Text>
      {detail ? (
        <Text variant="caption" tone="muted" style={styles.detail}>
          {detail}
        </Text>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.sm,
    minHeight: 36,
    paddingHorizontal: space.md,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  pressed: { backgroundColor: colors.pressed },
  selected: { borderColor: colors.accent, backgroundColor: colors.accentSoft },
  disabled: { opacity: 0.5 },
  detail: { fontVariant: ['tabular-nums'] },
});
