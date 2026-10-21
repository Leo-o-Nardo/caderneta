import { Receipt } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { cancelAction, confirmAction } from '@/api/assistant';
import { useCards } from '@/api/cards';
import { useCategories } from '@/api/categories';
import type { TransactionsConfirmCard } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { PAYMENT_LABELS } from '@/components/TransactionRow';
import { Amount, Card, Text } from '@/components/ui';
import {
  firstItemWithError,
  isExpired,
  itemErrors,
  itemsCountLabel,
  transactionDrafts,
  transactionsPayload,
  type TransactionDraft,
} from '@/lib/actions';
import { dayLabel, monthName } from '@/lib/dates';
import { invoiceMonthFor } from '@/lib/invoice';
import { formatCents } from '@/lib/money';
import { useDraft } from '@/stores/actionDrafts';
import { colors, space } from '@/theme/tokens';

import { CardActions } from './CardActions';
import { FichaHeader, FichaTotal } from './Ficha';
import { TransactionItemEditor } from './TransactionItemEditor';
import { useCardAction } from './useCardAction';

/**
 * Os lançamentos que o assistente entendeu, para conferir. Tocar numa linha abre a edição logo
 * abaixo; o total (com o traço duplo) e o "Confirmar" acompanham. Depois da decisão, a ficha mostra
 * o que ficou gravado.
 */
export function TransactionsCard({ card }: { card: TransactionsConfirmCard }) {
  const pending = card.action.status === 'pending' && !isExpired(card.action);
  const [drafts, setDrafts] = useDraft(card.action.id, () => transactionDrafts(card));
  const [open, setOpen] = useState<number | null>(null);
  const { busy, error, run } = useCardAction();
  const categories = useCategories();
  const cards = useCards();

  // Pendente: o rascunho com as edições. Depois: o que veio da API (o que foi gravado).
  const items = pending ? drafts : transactionDrafts(card);
  const total = items.reduce((sum, item) => sum + item.amount_cents, 0);
  const dates = [...new Set(items.map((item) => item.occurred_on))];
  const count = itemsCountLabel(items.map((item) => item.kind));
  // A data vai no topo quando é uma só e já foi decidido; senão, em cada linha.
  const datePerItem = pending || dates.length > 1;

  const update = (index: number, item: TransactionDraft) =>
    setDrafts(drafts.map((current, position) => (position === index ? item : current)));

  async function confirm() {
    const failure = await run('confirm', () => confirmAction(card.action.id, transactionsPayload(drafts)));
    const withError = failure ? firstItemWithError(failure.fieldErrors) : null;

    // O item com erro abre sozinho, com a mensagem embaixo do campo.
    if (withError !== null) {
      setOpen(withError);
    }
  }

  // Erros de item ficam nos itens; no rodapé, só o que não é de nenhum ("expirou", "já confirmada").
  const generalError = error?.messageOutside(Object.keys(error.fieldErrors).filter((field) => field.startsWith('items.')));

  return (
    <Card>
      <FichaHeader
        icon={Receipt}
        title={pending ? `Conferir · ${count}` : count}
        aside={pending ? 'toque para corrigir' : dates.length === 1 ? dayLabel(dates[0]) : null}
      />

      {items.map((item, index) => {
        const category = categories.data?.find((option) => option.id === item.category_id) ?? card.items[index]?.category;
        const creditCard = cards.data?.find((option) => option.id === item.card_id);
        const payment =
          item.kind === 'income'
            ? null
            : item.payment_type === 'credit_card'
              ? `Crédito${creditCard ? ` ${creditCard.name}` : ''}`
              : item.payment_type
                ? PAYMENT_LABELS[item.payment_type]
                : null;
        const details = [category?.name, payment, datePerItem ? dayLabel(item.occurred_on).toLowerCase() : null]
          .filter(Boolean)
          .join(' · ');
        const isOpen = pending && open === index;

        return (
          <View key={index}>
            <Pressable
              disabled={!pending}
              onPress={() => setOpen(isOpen ? null : index)}
              style={({ pressed }) => [styles.row, (isOpen || pressed) && styles.rowOpen]}
              accessibilityRole="button"
              accessibilityState={{ expanded: isOpen }}
              accessibilityHint="Toque para corrigir"
            >
              {category ? <CategoryIcon icon={category.icon} color={category.color} /> : <View style={styles.iconSpace} />}
              <View style={styles.texts}>
                <Text variant="strong" numberOfLines={1}>
                  {item.description}
                </Text>
                <Text variant="caption" tone="muted" numberOfLines={1}>
                  {details}
                </Text>
                {item.installments && creditCard ? (
                  // Onde cai a 1ª parcela, pela mesma regra de fatura do backend.
                  <Text variant="caption" tone="muted" numberOfLines={1}>
                    1ª parcela na fatura de {monthName(invoiceMonthFor(creditCard.closing_day, item.occurred_on))}
                  </Text>
                ) : null}
              </View>
              <View style={styles.amount}>
                {item.installments ? (
                  <>
                    <Text variant="amount">
                      {item.installments}× {formatCents(Math.floor(item.amount_cents / item.installments))}
                    </Text>
                    <Text variant="caption" tone="muted">
                      {formatCents(item.amount_cents)}
                    </Text>
                  </>
                ) : (
                  <Amount cents={item.amount_cents} kind={item.kind === 'income' ? 'income' : 'expense'} />
                )}
              </View>
            </Pressable>

            {isOpen ? (
              <TransactionItemEditor
                item={item}
                onChange={(next) => update(index, next)}
                errors={error ? itemErrors(error.fieldErrors, index) : {}}
              />
            ) : null}
          </View>
        );
      })}

      {items.length > 1 ? <FichaTotal cents={total} /> : null}

      <CardActions
        action={card.action}
        confirmLabel={`Confirmar ${formatCents(total)}`}
        confirmDisabled={drafts.some((draft) => draft.amount_cents === 0)}
        busy={busy}
        error={generalError}
        onConfirm={confirm}
        onCancel={() => run('cancel', () => cancelAction(card.action.id))}
      />
    </Card>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingHorizontal: space.lg,
    paddingVertical: space.md,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  rowOpen: { backgroundColor: colors.pressed },
  iconSpace: { width: 24 },
  texts: { flex: 1, gap: space.xs },
  amount: { alignItems: 'flex-end' },
});
