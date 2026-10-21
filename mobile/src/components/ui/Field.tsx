import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  label: string;
  error?: string;
  hint?: string;
  children: ReactNode;
};

/** Um bloco de formulário: o rótulo em cima, o conteúdo (tags, seletores) e o erro da API embaixo. */
export function Field({ label, error, hint, children }: Props) {
  return (
    <View style={styles.field}>
      <Text variant="label">{label}</Text>
      {children}
      {error ? (
        <Text variant="caption" tone="danger">
          {error}
        </Text>
      ) : hint ? (
        <Text variant="caption" tone="muted">
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  field: { gap: space.sm },
});
