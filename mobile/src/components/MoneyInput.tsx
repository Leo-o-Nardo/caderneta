import { StyleSheet, TextInput, View } from 'react-native';

import { Text } from '@/components/ui';
import { centsFromDigits, formatCents } from '@/lib/money';
import { colors, font, space } from '@/theme/tokens';

type Props = {
  // Em centavos.
  value: number;
  onChange: (cents: number) => void;
  autoFocus?: boolean;
  editable?: boolean;
  error?: string;
};

// Até R$ 999.999.999,99: o limite da API é maior, isso só evita números absurdos na tela.
const MAX_DIGITS = 11;

/**
 * O valor grande do formulário, digitado como em app de banco: cada dígito entra pela direita
 * ("4", "45", "450", "4500" → R$ 45,00). Um campo invisível por cima recebe o teclado numérico.
 */
export function MoneyInput({ value, onChange, autoFocus = false, editable = true, error }: Props) {
  return (
    <View style={styles.container}>
      {/* O valor escrito numa linha, como na caderneta. */}
      <View style={styles.line}>
        <Text style={[styles.amount, value === 0 && styles.placeholder]}>{formatCents(value)}</Text>
        <TextInput
          value={value === 0 ? '' : String(value)}
          onChangeText={(text) => onChange(centsFromDigits(text))}
          keyboardType="number-pad"
          maxLength={MAX_DIGITS}
          autoFocus={autoFocus}
          editable={editable}
          caretHidden
          contextMenuHidden
          accessibilityLabel={`Valor: ${formatCents(value)}`}
          style={styles.hiddenInput}
        />
      </View>
      {error ? (
        <Text variant="caption" tone="danger">
          {error}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { alignItems: 'center', gap: space.xs, paddingVertical: space.lg },
  line: { alignSelf: 'stretch', alignItems: 'center', paddingBottom: space.sm, borderBottomWidth: 1, borderBottomColor: colors.ink },
  amount: {
    fontFamily: font.serif,
    fontSize: 40,
    lineHeight: 48,
    color: colors.ink,
    letterSpacing: -0.8,
    fontVariant: ['lining-nums', 'tabular-nums'],
  },
  placeholder: { color: colors.faint },
  hiddenInput: { ...StyleSheet.absoluteFill, opacity: 0, color: 'transparent' },
});
