import { useMutation } from '@tanstack/react-query';
import { useLocalSearchParams } from 'expo-router';
import { Check } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { alertAction } from '@/api/alerts';
import { payBill, useBill } from '@/api/bills';
import type { BillDetail, PaymentType } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { DateField } from '@/components/DateField';
import { MoneyInput } from '@/components/MoneyInput';
import { PaymentPicker } from '@/components/PaymentPicker';
import { Button, Form, Header, Screen, Text } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { monthLabel, todayISO } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { closeAndRefresh } from '@/lib/navigation';
import { colors, space } from '@/theme/tokens';

/**
 * O "Paguei": marca o mês da conta como pago e lança a despesa. Vindo de um aviso do Chat
 * (alert_id), o card do aviso passa a mostrar "Paga".
 */
export default function PayBillScreen() {
  const params = useLocalSearchParams<{ bill_id: string; month?: string; alert_id?: string }>();
  const query = useBill(Number(params.bill_id));

  return (
    <Screen>
      <Header title="Paguei" leading="close" />

      {query.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : query.isError ? (
        <Text tone="danger" style={styles.status}>
          {query.error.message}
        </Text>
      ) : (
        <PayBillForm
          bill={query.data}
          month={params.month ?? query.data.open?.month ?? null}
          alertId={params.alert_id ? Number(params.alert_id) : null}
        />
      )}
    </Screen>
  );
}

const VISIBLE_FIELDS = ['amount_cents', 'paid_on', 'payment_type', 'card_id'];

function PayBillForm({ bill, month, alertId }: { bill: BillDetail; month: string | null; alertId: number | null }) {
  // Valor fixo já vem preenchido; valor variável começa vazio, com o teclado aberto, para digitar o exato.
  const [amount, setAmount] = useState(bill.amount_varies ? 0 : (bill.expected_cents ?? 0));
  const [date, setDate] = useState(todayISO());
  // Conta paga: Pix primeiro (a ordem para contas é Pix, débito, dinheiro e cartão).
  const [paymentType, setPaymentType] = useState<PaymentType>('pix');
  const [cardId, setCardId] = useState<number | null>(null);

  const pay = useMutation({
    mutationFn: () =>
      payBill(bill.id, {
        month: month ?? '',
        amount_cents: amount,
        paid_on: date,
        payment_type: paymentType,
        card_id: paymentType === 'credit_card' ? cardId : null,
      }),
    onSuccess: async () => {
      // Pago a partir de um aviso: o card do aviso vira "Paga". Se isso falhar, o pagamento já está feito.
      if (alertId !== null) {
        await alertAction(alertId, 'paid').catch(() => {});
      }

      closeAndRefresh();
    },
  });

  const error = pay.error instanceof ApiError ? pay.error : null;

  if (!month) {
    return (
      <Text tone="muted" style={styles.status}>
        Esta conta está em dia: não há mês em aberto para pagar.
      </Text>
    );
  }

  return (
    <Form
      error={error?.messageOutside(VISIBLE_FIELDS)}
      submit={
        <Button
          title="Confirmar pagamento"
          icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={pay.isPending}
          disabled={amount === 0}
          onPress={() => pay.mutate()}
        />
      }
    >
      {/* A conta e o mês, direto no papel, como uma linha da Agenda. */}
      <View style={styles.bill}>
        <CategoryIcon icon={bill.category.icon} color={bill.category.color} />
        <View style={styles.flex}>
          <Text variant="subtitle">{bill.name}</Text>
          <Text variant="caption" tone="muted">
            {monthLabel(month)}
          </Text>
        </View>
      </View>

      <View>
        <MoneyInput value={amount} onChange={setAmount} autoFocus={bill.amount_varies} error={error?.field('amount_cents')} />
        {bill.amount_varies && bill.estimate_cents ? (
          <Text variant="caption" tone="muted" style={styles.center}>
            O valor varia. Estimativa: ~{formatCents(bill.estimate_cents)}
          </Text>
        ) : null}
      </View>

      <DateField label="Pago em" value={date} onChange={setDate} error={error?.field('paid_on')} />

      <PaymentPicker
        mode="bill"
        paymentType={paymentType}
        cardId={cardId}
        onChange={(type, card) => {
          setPaymentType(type);
          setCardId(card);
        }}
        date={date}
        error={error?.field('payment_type') ?? error?.field('card_id')}
      />
    </Form>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  bill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  flex: { flex: 1, gap: space.xs },
  center: { textAlign: 'center' },
});
