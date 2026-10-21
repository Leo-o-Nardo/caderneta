import { Check, Undo2 } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { undoAction } from '@/api/assistant';
import type { DoneActionCard } from '@/api/types';
import { Button, Card, Text } from '@/components/ui';
import { doneCardText } from '@/lib/actions';
import { colors, space } from '@/theme/tokens';

import { useCardAction, useUndoSecondsLeft } from './useCardAction';

/**
 * O que já foi feito na hora (itens na lista, lembrete, lista nova, conta fixa, receita fixa,
 * orçamento): ✓, o resumo e "Desfazer" enquanto vale (2 minutos). Depois de desfeito, fica
 * registrado na ficha.
 */
export function DoneCard({ card }: { card: DoneActionCard }) {
  const { title, lines } = doneCardText(card);
  const secondsLeft = useUndoSecondsLeft(card.action);
  const { busy, error, run } = useCardAction();
  const undone = card.action.status === 'undone';

  return (
    <Card style={styles.card}>
      <View style={styles.iconColumn}>
        {undone ? (
          <Undo2 size={20} color={colors.faint} strokeWidth={1.6} />
        ) : (
          <Check size={20} color={colors.accent} strokeWidth={2} />
        )}
      </View>

      <View style={styles.texts}>
        <Text variant="strong" tone={undone ? 'faint' : 'ink'} style={undone ? styles.undone : undefined}>
          {title}
        </Text>
        {lines.map((line) => (
          <Text key={line} variant="caption" tone="muted">
            {line}
          </Text>
        ))}
        {undone ? (
          <Text variant="caption" tone="faint">
            Desfeito
          </Text>
        ) : null}
        {error ? (
          <Text variant="caption" tone="danger">
            {error.message}
          </Text>
        ) : null}
        {secondsLeft > 0 && !undone ? (
          <Button
            title="Desfazer"
            variant="ghost"
            icon={<Undo2 size={18} color={colors.accent} strokeWidth={1.6} />}
            loading={busy === 'undo'}
            disabled={busy !== null}
            onPress={() => run('undo', () => undoAction(card.action.id))}
            style={styles.undo}
          />
        ) : null}
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  card: { flexDirection: 'row', alignItems: 'flex-start', gap: space.md, padding: space.lg },
  iconColumn: { width: 24, alignItems: 'center' },
  texts: { flex: 1, gap: space.xs },
  undone: { textDecorationLine: 'line-through' },
  undo: { marginTop: space.xs },
});
