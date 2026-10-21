import { StyleSheet, TextInput, View } from 'react-native';

import { centsFromDigits, formatCents } from '@/lib/money';
import { colors, font, radius, space } from '@/theme/tokens';

import { Field } from './Field';
import { Text } from './Text';

type Props = {
  label: string;
  // Em centavos; null = ainda sem valor (conta de valor variável).
  value: number | null;
  onChange: (cents: number) => void;
  error?: string;
  hint?: string;
};

// Até R$ 999.999.999,99, como no campo grande.
const MAX_DIGITS = 11;

/**
 * O campo de valor pequeno, para dentro dos cards do chat. Digita como app de banco ("4", "45",
 * "450" → R$ 4,50), igual ao MoneyInput grande do formulário.
 */
export function CompactMoneyInput({ label, value, onChange, error, hint }: Props) {
  const empty = value === null || value === 0;

  return (
    <Field label={label} error={error} hint={hint}>
      <View style={[styles.box, Boolean(error) && styles.invalid]}>
        <Text style={[styles.amount, empty && styles.placeholder]}>{formatCents(value ?? 0)}</Text>
        <TextInput
          value={empty ? '' : String(value)}
          onChangeText={(text) => onChange(centsFromDigits(text))}
          keyboardType="number-pad"
          maxLength={MAX_DIGITS}
          caretHidden
          contextMenuHidden
          accessibilityLabel={`${label}: ${formatCents(value ?? 0)}`}
          style={styles.hiddenInput}
        />
      </View>
    </Field>
  );
}

const styles = StyleSheet.create({
  box: {
    minHeight: 48,
    justifyContent: 'center',
    paddingHorizontal: space.md,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
  },
  invalid: { borderColor: colors.danger, boxShadow: `inset 0 0 0 1px ${colors.danger}` },
  amount: { fontFamily: font.semibold, fontSize: 18, lineHeight: 24, color: colors.ink, fontVariant: ['tabular-nums'] },
  placeholder: { color: colors.faint },
  hiddenInput: { ...StyleSheet.absoluteFill, opacity: 0, color: 'transparent' },
});
