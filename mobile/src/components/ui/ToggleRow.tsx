import { StyleSheet, Switch, View } from 'react-native';

import { colors, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  label: string;
  description?: string;
  value: boolean;
  onChange: (value: boolean) => void;
  disabled?: boolean;
};

/**
 * Uma opção liga/desliga com explicação embaixo ("Só eu vejo", "Parcelado"). O trilho desligado
 * usa a Borda de campo (3:1 sobre o papel) e o ligado, Musgo.
 */
export function ToggleRow({ label, description, value, onChange, disabled = false }: Props) {
  return (
    <View style={styles.row}>
      <View style={styles.texts}>
        <Text variant="strong">{label}</Text>
        {description ? (
          <Text variant="caption" tone="muted">
            {description}
          </Text>
        ) : null}
      </View>
      <Switch
        value={value}
        onValueChange={onChange}
        disabled={disabled}
        trackColor={{ false: colors.border, true: colors.accent }}
        ios_backgroundColor={colors.border}
        thumbColor={colors.surface}
        accessibilityLabel={label}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'center', gap: space.lg },
  texts: { flex: 1, gap: space.xs },
});
