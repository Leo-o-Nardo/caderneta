import type { ReactNode } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

export type LedgerCell = {
  label: string;
  // O valor, normalmente um <Amount variant="figure" />.
  value: ReactNode;
  // A legenda embaixo do valor ("até hoje, dia 19", "Delivery estourou").
  note?: ReactNode;
  // Com toque, o rótulo ganha a setinha ("Receitas ›").
  onPress?: () => void;
};

/**
 * As colunas de caderneta do guia: rótulo, valor em Fraunces e legenda, lado a lado, com a pauta
 * em cima, embaixo e entre elas. No Início ("Despesas de outubro" e "Resta nos orçamentos") e no
 * Resumo (Receitas e Despesas).
 */
export function Ledger({ cells }: { cells: LedgerCell[] }) {
  return (
    <View style={styles.ledger}>
      {cells.map((cell, index) => (
        <Cell key={cell.label} cell={cell} divided={index > 0} />
      ))}
    </View>
  );
}

function Cell({ cell, divided }: { cell: LedgerCell; divided: boolean }) {
  const content = (
    <>
      <Text variant="caption" tone="muted">
        {cell.onPress ? `${cell.label} ›` : cell.label}
      </Text>
      {cell.value}
      {cell.note}
    </>
  );

  if (!cell.onPress) {
    return <View style={[styles.cell, divided && styles.divided]}>{content}</View>;
  }

  return (
    <Pressable
      onPress={cell.onPress}
      style={({ pressed }) => [styles.cell, divided && styles.divided, pressed && styles.pressed]}
      accessibilityRole="button"
    >
      {content}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  ledger: { flexDirection: 'row', borderTopWidth: 1, borderBottomWidth: 1, borderColor: colors.line },
  cell: { flex: 1, gap: space.xs, paddingVertical: space.md },
  divided: { borderLeftWidth: 1, borderLeftColor: colors.line, paddingLeft: space.lg },
  pressed: { backgroundColor: colors.pressed },
});
