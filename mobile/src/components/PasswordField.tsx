import { Check, Circle } from 'lucide-react-native';
import type { ComponentProps } from 'react';
import { StyleSheet, View } from 'react-native';

import { Text, TextField } from '@/components/ui';
import { passwordChecks } from '@/lib/password';
import { colors, space } from '@/theme/tokens';

type Props = Omit<ComponentProps<typeof TextField>, 'password' | 'hint' | 'value'> & {
  value: string;
};

/**
 * A senha nova (guia 1.4): um campo só, com o olho para mostrar o que foi digitado, e embaixo as
 * três marcas da regra, que ficam em Musgo conforme a senha atende a cada uma.
 */
export function PasswordField({ value, ...props }: Props) {
  const checks = passwordChecks(value);

  return (
    <View style={styles.wrapper}>
      <TextField {...props} value={value} password autoComplete="new-password" textContentType="newPassword" />
      <View
        style={styles.checks}
        accessible
        accessibilityLabel={`A senha precisa de: ${checks.map((check) => `${check.label}, ${check.ok ? 'feito' : 'falta'}`).join('; ')}`}
      >
        {checks.map((check) => (
          <View key={check.label} style={styles.check}>
            {/* A caixa de 16 alinha o círculo pequeno com o ✓. */}
            <View style={styles.mark}>
              {check.ok ? (
                <Check size={16} color={colors.accent} strokeWidth={1.6} />
              ) : (
                <Circle size={10} color={colors.faint} strokeWidth={1.6} />
              )}
            </View>
            <Text variant="caption" tone={check.ok ? 'accent' : 'muted'}>
              {check.label}
            </Text>
          </View>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: { gap: space.sm },
  checks: { flexDirection: 'row', flexWrap: 'wrap', columnGap: space.md, rowGap: space.xs },
  check: { flexDirection: 'row', alignItems: 'center', gap: space.xs },
  mark: { width: 16, height: 16, alignItems: 'center', justifyContent: 'center' },
});
