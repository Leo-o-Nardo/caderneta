import { Pressable, StyleSheet, View } from 'react-native';

import { colors, radius } from '@/theme/tokens';

import { Text } from './Text';

type Props<T extends string> = {
  options: { value: T; label: string }[];
  value: T;
  onChange: (value: T) => void;
  disabled?: boolean;
};

/**
 * Botões lado a lado, um escolhido por vez: "Despesa | Receita", "Tudo | Despesas | Receitas".
 * Contorno de Tinta; o escolhido fica preenchido de Tinta.
 */
export function SegmentedControl<T extends string>({ options, value, onChange, disabled = false }: Props<T>) {
  return (
    <View style={[styles.container, disabled && styles.disabled]}>
      {options.map((option, index) => {
        const selected = option.value === value;

        return (
          <Pressable
            key={option.value}
            accessibilityRole="button"
            accessibilityState={{ selected, disabled }}
            disabled={disabled}
            onPress={() => onChange(option.value)}
            style={({ pressed }) => [
              styles.option,
              index > 0 && styles.divider,
              pressed && !selected && styles.pressed,
              selected && styles.selected,
            ]}
          >
            <Text variant="label" tone={selected ? 'canvas' : 'ink'}>
              {option.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    borderWidth: 1,
    borderColor: colors.ink,
    borderRadius: radius.md,
    overflow: 'hidden',
  },
  option: { flex: 1, minHeight: 40, alignItems: 'center', justifyContent: 'center' },
  divider: { borderLeftWidth: 1, borderLeftColor: colors.ink },
  pressed: { backgroundColor: colors.pressed },
  selected: { backgroundColor: colors.ink },
  disabled: { opacity: 0.6 },
});
