import { ListChecks, Trash2 } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { cancelAction, confirmAction } from '@/api/assistant';
import type { DeleteConfirmCard, ListDeleteCard } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { Amount, Card, Text } from '@/components/ui';
import { isExpired } from '@/lib/actions';
import { shortDate } from '@/lib/dates';
import { colors, space } from '@/theme/tokens';

import { CardActions } from './CardActions';
import { FichaHeader, FichaItem } from './Ficha';
import { useCardAction } from './useCardAction';

/**
 * "Apaga o uber de ontem" ou "apaga a lista de churrasco": o que vai sumir e o impacto em Tijolo,
 * antes de apagar de vez.
 */
export function DeleteCard({ card }: { card: DeleteConfirmCard | ListDeleteCard }) {
  const pending = card.action.status === 'pending' && !isExpired(card.action);
  const { busy, error, run } = useCardAction();
  const deleted = !pending && card.action.status === 'executed';

  return (
    <Card>
      <FichaHeader
        icon={Trash2}
        title={card.type === 'list_delete' ? 'Apagar lista' : 'Apagar lançamento'}
        tone={pending ? 'danger' : 'muted'}
      />

      {card.type === 'list_delete' ? (
        <FichaItem
          icon={
            <View style={styles.iconColumn}>
              <ListChecks size={20} color={colors.muted} strokeWidth={1.6} />
            </View>
          }
          title={`Lista ${card.list.name}`}
          struck={deleted}
        />
      ) : (
        <FichaItem
          icon={<CategoryIcon icon={card.transaction.category.icon} color={card.transaction.category.color} />}
          title={card.transaction.description}
          detail={`${shortDate(card.transaction.occurred_on)} · ${card.transaction.category.name}`}
          value={<Amount cents={card.transaction.amount_cents} />}
          struck={deleted}
        />
      )}

      {pending ? (
        <Text variant="caption" tone="danger" style={styles.impact}>
          {card.impact}
        </Text>
      ) : null}

      <CardActions
        action={card.action}
        confirmLabel="Apagar"
        cancelLabel="Manter"
        confirmIcon={Trash2}
        danger
        busy={busy}
        error={error?.message}
        onConfirm={() => run('confirm', () => confirmAction(card.action.id))}
        onCancel={() => run('cancel', () => cancelAction(card.action.id))}
      />
    </Card>
  );
}

const styles = StyleSheet.create({
  iconColumn: { width: 24, alignItems: 'center' },
  impact: { paddingHorizontal: space.lg, paddingBottom: space.sm },
});
