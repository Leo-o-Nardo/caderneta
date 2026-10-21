import { StyleSheet, View } from 'react-native';

import { CategoryPicker } from '@/components/CategoryPicker';
import { DateField } from '@/components/DateField';
import { PaymentPicker } from '@/components/PaymentPicker';
import { CompactMoneyInput, Field, Stepper, Text, TextField } from '@/components/ui';
import type { TransactionDraft } from '@/lib/actions';
import { formatCents } from '@/lib/money';
import { colors, space } from '@/theme/tokens';

type Props = {
  item: TransactionDraft;
  onChange: (item: TransactionDraft) => void;
  // Os erros deste item que a API devolveu, por campo.
  errors: Record<string, string>;
};

/**
 * A linha aberta de um lançamento do card (tela 2 do esboço): valor, descrição, categoria, data e
 * pagamento, com os mesmos componentes do formulário de "Novo lançamento".
 */
export function TransactionItemEditor({ item, onChange, errors }: Props) {
  const set = (changes: Partial<TransactionDraft>) => onChange({ ...item, ...changes });

  return (
    <View style={styles.editor}>
      <CompactMoneyInput
        label={item.installments ? 'Valor total' : 'Valor'}
        value={item.amount_cents}
        onChange={(amount_cents) => set({ amount_cents })}
        error={errors.amount_cents ?? errors.total_cents}
      />

      <TextField
        label="Descrição"
        value={item.description}
        onChangeText={(description) => set({ description })}
        maxLength={120}
        error={errors.description}
      />

      <Field label="Categoria" error={errors.category_id}>
        <CategoryPicker kind={item.kind} value={item.category_id} onChange={(category_id) => set({ category_id })} />
      </Field>

      <DateField value={item.occurred_on} onChange={(occurred_on) => set({ occurred_on })} error={errors.occurred_on ?? errors.purchased_on} />

      {item.kind === 'expense' ? (
        <PaymentPicker
          paymentType={item.payment_type ?? 'pix'}
          cardId={item.card_id}
          date={item.occurred_on}
          installments={Boolean(item.installments)}
          // Parcelado só existe no crédito: trocar a forma de pagamento desfaz as parcelas.
          onChange={(payment_type, card_id) =>
            set({ payment_type, card_id, installments: payment_type === 'credit_card' ? item.installments : null })
          }
          error={errors.payment_type ?? errors.card_id}
        />
      ) : null}

      {item.installments ? (
        <Stepper
          value={item.installments}
          onChange={(installments) => set({ installments })}
          min={2}
          max={48}
          format={(value) => `${value}×`}
          suffix={`de ${formatCents(Math.floor(item.amount_cents / item.installments))}`}
          label="parcelas"
        />
      ) : null}

      {errors.installments ? (
        <Text variant="caption" tone="danger">
          {errors.installments}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  // A linha aberta fica no Papel, um tom abaixo da Folha da ficha.
  editor: { gap: space.lg, padding: space.lg, backgroundColor: colors.canvas },
});
