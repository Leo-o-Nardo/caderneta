import { useMutation } from '@tanstack/react-query';
import { Check, Plus } from 'lucide-react-native';
import { useState } from 'react';

import { createBill, updateBill } from '@/api/bills';
import type { Bill } from '@/api/types';
import { Button, Field, Form, TextField, ToggleRow } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { closeAndRefresh } from '@/lib/navigation';
import { colors } from '@/theme/tokens';

import { CategoryPicker } from './CategoryPicker';
import { DueDayPicker } from './DueDayPicker';
import { MoneyInput } from './MoneyInput';

const VISIBLE_FIELDS = ['name', 'category_id', 'expected_cents', 'due_day'];

type Props = {
  // Sem conta: nova. Com conta: edição.
  bill?: Bill;
};

/** Nome, categoria, valor (ou estimativa, se o valor varia), dia do vencimento e débito automático. */
export function BillForm({ bill }: Props) {
  const [name, setName] = useState(bill?.name ?? '');
  const [categoryId, setCategoryId] = useState<number | null>(bill?.category.id ?? null);
  const [varies, setVaries] = useState(bill?.amount_varies ?? false);
  const [amount, setAmount] = useState(bill?.expected_cents ?? 0);
  const [dueDay, setDueDay] = useState(bill?.due_day ?? 10);
  const [autopay, setAutopay] = useState(bill?.autopay ?? false);

  const save = useMutation({
    mutationFn: (): Promise<unknown> => {
      const input = {
        name,
        category_id: categoryId ?? 0,
        // Zero = sem valor: na conta variável, sem estimativa; na fixa, o valor vai a cada pagamento.
        expected_cents: amount > 0 ? amount : null,
        amount_varies: varies,
        due_day: dueDay,
        autopay,
      };

      return bill ? updateBill(bill.id, input) : createBill(input);
    },
    onSuccess: closeAndRefresh,
  });

  const error = save.error instanceof ApiError ? save.error : null;

  return (
    <Form
      error={error?.messageOutside(VISIBLE_FIELDS)}
      submit={
        <Button
          title={bill ? 'Salvar' : 'Cadastrar conta'}
          icon={bill ? <Check size={18} color={colors.canvas} strokeWidth={1.6} /> : <Plus size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={save.isPending}
          onPress={() => save.mutate()}
        />
      }
    >
      <TextField
        label="Nome"
        value={name}
        onChangeText={setName}
        placeholder="Ex.: Luz, Aluguel, Internet"
        maxLength={60}
        error={error?.field('name')}
      />

      <Field label="Categoria" error={error?.field('category_id')}>
        <CategoryPicker kind="expense" value={categoryId} onChange={setCategoryId} />
      </Field>

      <ToggleRow
        label="O valor muda todo mês"
        description="Como luz e água: você digita o valor exato ao pagar."
        value={varies}
        onChange={setVaries}
      />

      <Field
        label={varies ? 'Estimativa (opcional)' : 'Valor'}
        error={error?.field('expected_cents')}
        hint={
          varies
            ? 'Entra nos totais até você pagar a conta algumas vezes; depois o app usa a média dos últimos 3 pagamentos.'
            : undefined
        }
      >
        <MoneyInput value={amount} onChange={setAmount} />
      </Field>

      <Field
        label="Vence todo dia"
        error={error?.field('due_day')}
        hint={dueDay > 28 ? 'Nos meses mais curtos, vence no último dia do mês.' : undefined}
      >
        <DueDayPicker value={dueDay} onChange={setDueDay} />
      </Field>

      <ToggleRow
        label="Débito automático"
        description="O banco paga sozinho: no dia do vencimento, o app lança a despesa no débito e avisa no Chat. Se o valor muda todo mês, o aviso pede o valor."
        value={autopay}
        onChange={setAutopay}
      />
    </Form>
  );
}
