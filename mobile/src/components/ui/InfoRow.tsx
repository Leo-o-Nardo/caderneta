import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';

import { colors, space } from '@/theme/tokens';

import { Text } from './Text';

type Props = {
  label: string;
  // Um texto ("todo dia 10") ou um valor pronto (<Amount />, o ícone com o nome da categoria).
  value: ReactNode;
};

/**
 * Um dado numa linha da caderneta: o rótulo em Grafite à esquerda, o valor à direita e a pauta
 * embaixo. Várias seguidas formam a tabela de dados do detalhe da conta e do orçamento.
 */
export function InfoRow({ label, value }: Props) {
  return (
    <View style={styles.row}>
      <Text tone="muted" style={styles.label}>
        {label}
      </Text>
      <View style={styles.value}>
        {typeof value === 'string' ? (
          <Text variant="strong" style={styles.text}>
            {value}
          </Text>
        ) : (
          value
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  label: { flex: 1 },
  // O rótulo nunca some: o valor comprido quebra a linha.
  value: { maxWidth: '60%', alignItems: 'flex-end' },
  text: { textAlign: 'right' },
});
