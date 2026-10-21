import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Check, Plus, Repeat, Trash2 } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { useCards } from '@/api/cards';
import { createInstallmentPlan, deleteInstallmentPlan, updateInstallmentPlan } from '@/api/installments';
import { createTransaction, deleteTransaction, updateTransaction } from '@/api/transactions';
import type { CategoryKind, PaymentType, Transaction } from '@/api/types';
import { Button, Field, Form, LinkRow, Notice, SegmentedControl, Stepper, Text, TextField, ToggleRow } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { shortDate, todayISO } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { closeAndRefresh } from '@/lib/navigation';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

import { CategoryPicker } from './CategoryPicker';
import { DateField } from './DateField';
import { MoneyInput } from './MoneyInput';
import { ownCard, PaymentPicker } from './PaymentPicker';
import { transactionDetails } from './TransactionRow';

// Os campos que a tela mostra (para os erros da API aparecerem embaixo de cada um).
const VISIBLE_FIELDS = [
  'amount_cents', 'total_cents', 'description', 'category_id', 'occurred_on', 'purchased_on',
  'payment_type', 'card_id', 'installments',
];

type Props = {
  // Sem lançamento: novo. Com lançamento: edição (ou só leitura, se a pessoa não pode alterar).
  transaction?: Transaction;
};

/**
 * O formulário de gasto ou receita. Uma compra no crédito marcada como "Parcelado" vira um
 * parcelamento. Numa parcela, só descrição, categoria e "só eu vejo" mudam, e valem para todas.
 * Uma receita nova marcada como "Repetir todo mês" vira também uma receita fixa (salário).
 */
export function TransactionForm({ transaction }: Props) {
  const editing = transaction !== undefined;
  const installment = transaction?.installment ?? null;
  const recurring = transaction?.recurring_income ?? null;
  const readOnly = editing && !transaction.can_edit;
  const lockMoney = readOnly || installment !== null;

  const [kind, setKind] = useState<CategoryKind>(transaction?.kind ?? 'expense');
  const [amount, setAmount] = useState(transaction?.amount_cents ?? 0);
  const [description, setDescription] = useState(transaction?.description ?? '');
  const [categoryId, setCategoryId] = useState<number | null>(transaction?.category.id ?? null);
  const [date, setDate] = useState(transaction?.occurred_on ?? todayISO());
  // null = a pessoa ainda não escolheu: vale o padrão de compra, calculado abaixo.
  const [chosenPayment, setChosenPayment] = useState<PaymentType | null>(transaction?.payment_type ?? null);
  const [chosenCardId, setChosenCardId] = useState<number | null>(transaction?.card?.id ?? null);
  const [split, setSplit] = useState(false);
  const [installments, setInstallments] = useState(2);
  const [isPrivate, setIsPrivate] = useState(transaction?.visibility === 'private');
  const [repeat, setRepeat] = useState(false);
  // O dia da data escolhida ("2026-10-05" → 5): é nele que a receita fixa cai nos próximos meses.
  const repeatDay = Number(date.slice(8, 10));

  // Compra nova: crédito no cartão de quem lança. Quem não tem cartão próprio começa no Pix.
  const cards = useCards();
  const myCard = ownCard(cards.data);
  const paymentType: PaymentType = chosenPayment ?? (cards.data && !myCard ? 'pix' : 'credit_card');
  const cardId = chosenCardId ?? (paymentType === 'credit_card' ? (myCard?.id ?? null) : null);

  const isSplit = !editing && kind === 'expense' && paymentType === 'credit_card' && split;

  const save = useMutation({
    // Conforme o caso, a resposta é um lançamento ou um parcelamento; a tela só precisa saber que deu certo.
    mutationFn: (): Promise<unknown> => {
      const visibility = isPrivate ? 'private' : 'household';
      const payment = kind === 'expense'
        ? { payment_type: paymentType, card_id: paymentType === 'credit_card' ? cardId : null }
        : {};

      if (installment) {
        return updateInstallmentPlan(installment.plan_id, { description, category_id: categoryId ?? 0, visibility });
      }

      if (transaction) {
        return updateTransaction(transaction.id, {
          amount_cents: amount,
          description,
          occurred_on: date,
          category_id: categoryId ?? 0,
          visibility,
          ...payment,
        });
      }

      if (isSplit) {
        return createInstallmentPlan({
          description,
          category_id: categoryId ?? 0,
          card_id: cardId ?? 0,
          installments,
          total_cents: amount,
          purchased_on: date,
          visibility,
        });
      }

      return createTransaction({
        kind,
        amount_cents: amount,
        description,
        occurred_on: date,
        category_id: categoryId ?? 0,
        visibility,
        ...payment,
        ...(kind === 'income' && repeat ? { repeat_monthly: true } : {}),
      });
    },
    onSuccess: closeAndRefresh,
  });

  const remove = useMutation({
    mutationFn: () => (installment ? deleteInstallmentPlan(installment.plan_id) : deleteTransaction(transaction?.id ?? 0)),
    onSuccess: closeAndRefresh,
  });

  const failure = save.error ?? remove.error;
  const error = failure instanceof ApiError ? failure : null;
  const generalError = error?.messageOutside(VISIBLE_FIELDS);

  function changeKind(value: CategoryKind) {
    setKind(value);
    // As categorias de despesa e de receita são outras.
    setCategoryId(null);
  }

  // A folha diz o que vai sumir e de onde: o lançamento, com o valor, sai das listas e dos totais.
  async function confirmDelete() {
    if (!transaction) {
      return;
    }

    const what = `${transaction.description} · ${formatCents(transaction.amount_cents)}.`;
    const confirmed = await confirm(
      installment
        ? {
            title: 'Apagar o parcelamento?',
            message: `${what} As ${installment.count} parcelas saem das faturas e dos totais de cada mês.`,
            confirmLabel: 'Apagar parcelamento',
            destructive: true,
          }
        : {
            title: kind === 'income' ? 'Apagar esta receita?' : 'Apagar este gasto?',
            message: `${what} Ele sai das listas e dos totais do mês${transaction.card ? ', e da fatura do cartão' : ''}.`,
            confirmLabel: kind === 'income' ? 'Apagar receita' : 'Apagar gasto',
            destructive: true,
          },
    );

    if (confirmed) {
      remove.mutate();
    }
  }

  // Só leitura: sem o botão principal (e sem rodapé).
  const submit = readOnly ? undefined : (
    <Button
      title={editing ? 'Salvar' : 'Lançar'}
      icon={
        editing ? <Check size={18} color={colors.canvas} strokeWidth={1.6} /> : <Plus size={18} color={colors.canvas} strokeWidth={1.6} />
      }
      loading={save.isPending}
      disabled={amount === 0}
      onPress={() => save.mutate()}
    />
  );

  return (
    <Form submit={submit} error={generalError}>
      {readOnly ? (
        <Notice>
          Lançado por {transaction.created_by?.name ?? 'outra pessoa'}. Só quem lançou ou o dono da casa pode alterar.
        </Notice>
      ) : null}
      {installment && !readOnly ? (
        <Notice>
          Parcela {installment.number} de {installment.count}. Mudar a descrição, a categoria ou o “só eu vejo” altera
          todas as parcelas.
        </Notice>
      ) : null}
      {recurring ? (
        // Mudar aqui altera só este mês; os próximos mudam na receita fixa.
        <LinkRow
          icon={<Repeat size={20} color={colors.muted} strokeWidth={1.6} />}
          label={`Repete todo mês, dia ${recurring.day_of_month}`}
          onPress={() => router.push({ pathname: '/recurring-incomes/[id]', params: { id: String(recurring.id) } })}
        />
      ) : null}

      <SegmentedControl
        options={[
          { value: 'expense', label: 'Despesa' },
          { value: 'income', label: 'Receita' },
        ]}
        value={kind}
        onChange={changeKind}
        disabled={editing}
      />

      <MoneyInput
        value={amount}
        onChange={setAmount}
        autoFocus={!editing}
        editable={!lockMoney}
        error={error?.field('amount_cents') ?? error?.field('total_cents')}
      />

      <TextField
        label="Descrição"
        value={description}
        onChangeText={setDescription}
        placeholder={kind === 'expense' ? 'Ex.: Almoço' : 'Ex.: Salário'}
        maxLength={120}
        editable={!readOnly}
        error={error?.field('description')}
      />

      <Field label="Categoria" error={error?.field('category_id')}>
        <CategoryPicker kind={kind} value={categoryId} onChange={setCategoryId} disabled={readOnly} />
      </Field>

      {lockMoney && transaction ? (
        // Valor, data e pagamento travados: mostra só o resumo.
        <Field label="Data e pagamento">
          <Text tone="muted">
            {shortDate(transaction.occurred_on)} · {transactionDetails(transaction)}
          </Text>
        </Field>
      ) : (
        <>
          <DateField value={date} onChange={setDate} error={error?.field('occurred_on') ?? error?.field('purchased_on')} />

          {kind === 'expense' ? (
            <PaymentPicker
              paymentType={paymentType}
              cardId={cardId}
              onChange={(type, card) => {
                setChosenPayment(type);
                setChosenCardId(card);
              }}
              date={date}
              installments={isSplit}
              error={error?.field('payment_type') ?? error?.field('card_id')}
            />
          ) : null}

          {!editing && kind === 'expense' && paymentType === 'credit_card' ? (
            <InstallmentsOptions
              split={split}
              onSplit={setSplit}
              installments={installments}
              onInstallments={setInstallments}
              amount={amount}
              error={error?.field('installments')}
            />
          ) : null}

          {!editing && kind === 'income' ? (
            <ToggleRow
              label="Repetir todo mês"
              description={
                `Lança sozinho todo dia ${repeatDay}` +
                (repeatDay > 28 ? ' (nos meses mais curtos, no último dia)' : '') +
                '. Para mudar ou parar: Agenda › Contas.'
              }
              value={repeat}
              onChange={setRepeat}
            />
          ) : null}
        </>
      )}

      <ToggleRow
        label="Só eu vejo"
        description="Os outros membros da casa não veem este lançamento, nem nos totais."
        value={isPrivate}
        onChange={setIsPrivate}
        disabled={readOnly}
      />

      {editing && !readOnly ? (
        <Button
          title={installment ? 'Apagar o parcelamento' : 'Apagar'}
          variant="dangerLink"
          icon={<Trash2 size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={remove.isPending}
          onPress={confirmDelete}
        />
      ) : null}
    </Form>
  );
}

type InstallmentsOptionsProps = {
  split: boolean;
  onSplit: (value: boolean) => void;
  installments: number;
  onInstallments: (value: number) => void;
  amount: number;
  error?: string;
};

/** "Parcelado" e a escolha do número de parcelas (2 a 48), com o valor de cada uma. */
function InstallmentsOptions({ split, onSplit, installments, onInstallments, amount, error }: InstallmentsOptionsProps) {
  return (
    <View style={styles.installments}>
      <ToggleRow label="Parcelado" value={split} onChange={onSplit} />

      {split ? (
        <Stepper
          value={installments}
          onChange={onInstallments}
          min={2}
          max={48}
          format={(value) => `${value}×`}
          suffix={`de ${formatCents(Math.floor(amount / installments))}`}
          label="parcelas"
        />
      ) : null}

      {error ? (
        <Text variant="caption" tone="danger">
          {error}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  installments: { gap: space.md },
});
