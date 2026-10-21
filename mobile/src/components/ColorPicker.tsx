import { Pressable, StyleSheet, View } from 'react-native';

import { CATEGORY_COLORS, colorName } from '@/lib/categories';
import { colors, radius, space } from '@/theme/tokens';

type Props = {
  value: string;
  onChange: (color: string) => void;
  // As cores que dá para escolher: as de categoria (o padrão) ou as de cartão.
  options?: readonly string[];
};

/**
 * As amostras de cor, como pastilhas de tinta (o guia deixa círculo só para o microfone e o
 * interruptor). A escolhida ganha o contorno Musgo, como nas outras escolhas.
 */
export function ColorPicker({ value, onChange, options = CATEGORY_COLORS }: Props) {
  return (
    <View style={styles.row}>
      {options.map((color) => {
        // A API guarda a cor como veio: compara sem ligar para maiúsculas.
        const selected = color.toUpperCase() === value.toUpperCase();

        return (
          <Pressable
            key={color}
            onPress={() => onChange(color)}
            style={({ pressed }) => [styles.cell, pressed && styles.pressed, selected && styles.selected]}
            accessibilityRole="button"
            accessibilityState={{ selected }}
            // O leitor de tela fala o nome do tom ("Cor Ameixa"), não o código.
            accessibilityLabel={`Cor ${colorName(color) ?? color}`}
          >
            <View style={[styles.swatch, { backgroundColor: color }]} />
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
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
  swatch: { width: 28, height: 28, borderRadius: radius.sm },
});
