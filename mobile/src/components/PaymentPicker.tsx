import { CreditCard } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import { useCards } from '@/api/cards';
import type { Card, PaymentType } from '@/api/types';
import { Chip, Field, Text } from '@/components/ui';
import { monthName } from '@/lib/dates';
import { invoiceMonthFor } from '@/lib/invoice';
import { space } from '@/theme/tokens';

import { PAYMENT_LABELS } from './TransactionRow';

// A ordem dos botões: numa compra o crédito vem primeiro; numa conta paga, o Pix.
const ORDER: Record<'purchase' | 'bill', PaymentType[]> = {
  purchase: ['credit_card', 'pix', 'debit', 'cash'],
  bill: ['pix', 'debit', 'cash', 'credit_card'],
};

type Props = {
  // "purchase" (padrão): lançamentos. "bill": pagar uma conta fixa.
  mode?: 'purchase' | 'bill';
  paymentType: PaymentType;
  cardId: number | null;
  onChange: (paymentType: PaymentType, cardId: number | null) => void;
  // A data da compra, para dizer em qual fatura ela cai.
  date: string;
  // Num parcelamento, a frase da fatura fala da 1ª parcela.
  installments?: boolean;
  error?: string;
};

/**
 * Crédito, Pix, Débito ou Dinheiro (numa conta, Pix primeiro). No crédito, a escolha do cartão (o
 * padrão de quem lança já vem marcado, com o ícone na cor do cartão) e a fatura em que a compra cai.
 */
export function PaymentPicker({ mode = 'purchase', paymentType, cardId, onChange, date, installments = false, error }: Props) {
  const cards = useCards();
  const card = cards.data?.find((item) => item.id === cardId);

  function choose(type: PaymentType) {
    const nextCard = type === 'credit_card' ? (cardId ?? defaultCard(cards.data)?.id ?? null) : cardId;
    onChange(type, nextCard);
  }

  return (
    <Field label="Pagamento" error={error}>
      <View style={styles.wrap}>
        {ORDER[mode].map((type) => (
          <Chip key={type} label={PAYMENT_LABELS[type]} selected={paymentType === type} onPress={() => choose(type)} />
        ))}
      </View>

      {paymentType === 'credit_card' ? (
        cards.data && cards.data.length > 0 ? (
          <View style={styles.cards}>
            <View style={styles.wrap}>
              {cards.data.map((item) => (
                <Chip
                  key={item.id}
                  label={item.is_mine ? item.name : `${item.name} · ${item.owner.name}`}
                  icon={<CreditCard size={18} color={item.color} strokeWidth={1.6} />}
                  selected={item.id === cardId}
                  onPress={() => onChange(paymentType, item.id)}
                />
              ))}
            </View>
            {card ? (
              <Text variant="caption" tone="muted">
                {installments ? '1ª parcela na fatura de ' : 'Cai na fatura de '}
                {monthName(invoiceMonthFor(card.closing_day, date))}.
              </Text>
            ) : null}
          </View>
        ) : (
          <Text variant="caption" tone="muted">
            Nenhum cartão cadastrado ainda. Os cartões ficam na aba Agenda.
          </Text>
        )
      ) : null}
    </Field>
  );
}

/** O cartão padrão de quem está lançando; senão, o primeiro cartão dele; senão, o primeiro da casa. */
function defaultCard(cards: Card[] | undefined): Card | undefined {
  return ownCard(cards) ?? cards?.[0];
}

/**
 * Só os cartões de quem lança: o padrão dele ou o primeiro. É o que uma compra nova usa sem a
 * pessoa escolher; o cartão de outra pessoa nunca entra sozinho.
 */
export function ownCard(cards: Card[] | undefined): Card | undefined {
  return cards?.find((card) => card.is_mine && card.is_default) ?? cards?.find((card) => card.is_mine);
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
  cards: { gap: space.sm, marginTop: space.xs },
});
