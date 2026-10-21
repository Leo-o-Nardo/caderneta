import { ArrowRight, Pencil } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { cancelAction, confirmAction } from '@/api/assistant';
import type { TransactionUpdateCard as Props } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { Amount, Card, Text } from '@/components/ui';
import { shortDate } from '@/lib/dates';
import { colors, space } from '@/theme/tokens';

import { CardActions } from './CardActions';
import { FichaHeader, FichaItem } from './Ficha';
import { useCardAction } from './useCardAction';

/**
 * "O café de ontem foi 15, não 12": o lançamento como estava e uma linha por campo que muda
 * (antes riscado, depois em destaque). Nada muda antes de confirmar; para outra correção, é só falar de novo.
 */
export function TransactionUpdateCard({ card }: { card: Props }) {
  const { busy, error, run } = useCardAction();
  const { transaction } = card;

  return (
    <Card>
      <FichaHeader icon={Pencil} title="Corrigir lançamento" />

      <FichaItem
        icon={<CategoryIcon icon={transaction.category.icon} color={transaction.category.color} />}
        title={transaction.description}
        detail={`${shortDate(transaction.occurred_on)} · ${transaction.category.name}`}
        value={<Amount cents={transaction.amount_cents} />}
      />

      <View style={styles.changes}>
        {card.changes.map((change) => (
          <View key={change.field} style={styles.change}>
            <Text variant="caption" tone="muted" style={styles.label}>
              {change.label}
            </Text>
            <Text variant="caption" tone="faint" style={styles.before} numberOfLines={1}>
              {change.before}
            </Text>
            <ArrowRight size={14} color={colors.faint} strokeWidth={1.6} />
            <Text variant="label" style={styles.after} numberOfLines={1}>
              {change.after}
            </Text>
          </View>
        ))}
      </View>

      <CardActions
        action={card.action}
        confirmLabel="Corrigir"
        busy={busy}
        error={error?.message}
        onConfirm={() => run('confirm', () => confirmAction(card.action.id))}
        onCancel={() => run('cancel', () => cancelAction(card.action.id))}
      />
    </Card>
  );
}

const styles = StyleSheet.create({
  changes: { gap: space.sm, paddingHorizontal: space.lg, paddingBottom: space.md },
  change: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  label: { width: 76 },
  before: { flexShrink: 1, textDecorationLine: 'line-through' },
  after: { flexShrink: 1 },
});
