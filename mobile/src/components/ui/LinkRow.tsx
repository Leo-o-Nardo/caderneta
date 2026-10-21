import { ChevronRight } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Pressable, StyleSheet } from 'react-native';

import { colors, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  // O ícone de 20 em Grafite, na coluna de 24 (guia, parte 04).
  icon?: ReactNode;
  label: string;
  onPress: () => void;
};

/** Uma linha que leva a outra tela, direto no papel entre duas pautas: o ícone, o texto e a seta fina. */
export function LinkRow({ icon, label, onPress }: Props) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, pressed && styles.pressed]} accessibilityRole="button">
      {icon}
      <Text variant="strong" style={styles.label}>
        {label}
      </Text>
      <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    minHeight: 48,
    paddingVertical: space.md,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: colors.line,
  },
  pressed: { backgroundColor: colors.pressed },
  label: { flex: 1 },
});
