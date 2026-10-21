import { Pressable, StyleSheet, View } from 'react-native';

import { colors, radius, space } from '@/theme/tokens';

import { CATEGORY_ICONS, IconGlyph } from './CategoryIcon';

type Props = {
  value: string;
  // A cor da categoria: o ícone escolhido aparece nela, como vai ficar nos lançamentos.
  color: string;
  onChange: (icon: string) => void;
};

/**
 * A grade com os ícones que a API aceita para categorias, em Grafite. O escolhido ganha a cor da
 * categoria e o contorno Musgo, como na grade de categorias do lançamento.
 */
export function IconPicker({ value, color, onChange }: Props) {
  return (
    <View style={styles.grid}>
      {CATEGORY_ICONS.map((key) => {
        const selected = key === value;

        return (
          <Pressable
            key={key}
            onPress={() => onChange(key)}
            style={({ pressed }) => [styles.cell, pressed && styles.pressed, selected && styles.selected]}
            accessibilityRole="button"
            accessibilityState={{ selected }}
            accessibilityLabel={`Ícone ${key}`}
          >
            <IconGlyph icon={key} size={24} color={selected ? color : colors.muted} />
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
  // 44 de lado: a área de toque mínima do guia.
  cell: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: { backgroundColor: colors.pressed },
  selected: { borderColor: colors.accent, backgroundColor: colors.accentSoft },
});
