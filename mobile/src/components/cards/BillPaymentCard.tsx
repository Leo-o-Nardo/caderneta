import { CalendarCheck, Check } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { cancelAction, confirmAction } from '@/api/assistant';
import type { BillConfirmCard } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { DateField } from '@/components/DateField';
import { PaymentPicker } from '@/components/PaymentPicker';
import { PAYMENT_LABELS } from '@/components/TransactionRow';
import { Amount, Card, CompactMoneyInput, Text } from '@/components/ui';
import { billDraft, billPayload, isExpired } from '@/lib/actions';
import { monthName, shortDate } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { useDraft } from '@/stores/actionDrafts';
import { colors, space } from '@/theme/tokens';

import { CardActions } from './CardActions';
import { FichaHeader, FichaItem } from './Ficha';
import { useCardAction } from './useCardAction';

const VISIBLE_FIELDS = ['amount_cents', 'paid_on', 'payment_type', 'card_id'];

/**
 * "Paguei a luz": a conta, o mês e os campos do pagamento já abertos. Em conta de valor variável
 * sem valor dito, o "Quanto deu?" vem vazio (com a média como dica) e só confirma preenchido.
 */
export function BillPaymentCard({ card }: { card: BillConfirmCard }) {
  const pending = card.action.status === 'pending' && !isExpired(card.action);
  const [draft, setDraft] = useDraft(card.action.id, () => billDraft(card));
  const { busy, error, run } = useCardAction();
  const shown = pending ? draft : billDraft(card);
  const detail = [
    card.due_on ? `vence ${shortDate(card.due_on)}` : null,
    !pending ? `${PAYMENT_LABELS[shown.payment_type]} · pago ${shortDate(shown.paid_on)}` : null,
  ]
    .filter(Boolean)
    .join(' · ');

  return (
    <Card>
      <FichaHeader icon={CalendarCheck} title="Conta paga" aside={monthName(card.month)} />

      <FichaItem
        icon={card.bill ? <CategoryIcon icon={card.bill.category.icon} color={card.bill.category.color} /> : undefined}
        title={card.bill?.name ?? 'Conta'}
        detail={detail || undefined}
        value={shown.amount_cents ? <Amount cents={shown.amount_cents} /> : <Text variant="amount" tone="muted">—</Text>}
      />

      {pending ? (
        <View style={styles.editor}>
          <CompactMoneyInput
            label={card.bill?.amount_varies ? 'Quanto deu?' : 'Valor'}
            value={draft.amount_cents}
            onChange={(amount_cents) => setDraft({ ...draft, amount_cents })}
            hint={card.estimate_cents ? `A média é ~${formatCents(card.estimate_cents)}` : undefined}
            error={error?.field('amount_cents')}
          />
          <DateField label="Pago em" value={draft.paid_on} onChange={(paid_on) => setDraft({ ...draft, paid_on })} error={error?.field('paid_on')} />
          <PaymentPicker
            mode="bill"
            paymentType={draft.payment_type}
            cardId={draft.card_id}
            date={draft.paid_on}
            onChange={(payment_type, card_id) => setDraft({ ...draft, payment_type, card_id })}
            error={error?.field('payment_type') ?? error?.field('card_id')}
          />
        </View>
      ) : null}

      <CardActions
        action={card.action}
        confirmLabel="Confirmar pagamento"
        confirmIcon={Check}
        confirmDisabled={!draft.amount_cents}
        busy={busy}
        error={error?.messageOutside(VISIBLE_FIELDS)}
        onConfirm={() => run('confirm', () => confirmAction(card.action.id, billPayload(card, draft)))}
        onCancel={() => run('cancel', () => cancelAction(card.action.id))}
      />
    </Card>
  );
}

const styles = StyleSheet.create({
  // Os campos do pagamento ficam no Papel, um tom abaixo da Folha da ficha.
  editor: { gap: space.lg, padding: space.lg, backgroundColor: colors.canvas },
});
