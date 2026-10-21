import { useMutation } from '@tanstack/react-query';
import { Check, Plus, RepeatOff } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { createRecurringIncome, stopRecurringIncome, updateRecurringIncome } from '@/api/recurringIncomes';
import type { RecurringIncome } from '@/api/types';
import { Button, Field, Form, Notice, TextField, ToggleRow } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { shortDate, todayISO } from '@/lib/dates';
import { closeAndRefresh } from '@/lib/navigation';
import { firstIncomeDate } from '@/lib/recurringIncomes';
import { confirm } from '@/stores/confirm';
import { colors } from '@/theme/tokens';

import { CategoryPicker } from './CategoryPicker';
import { DueDayPicker } from './DueDayPicker';
import { MoneyInput } from './MoneyInput';

const VISIBLE_FIELDS = ['amount_cents', 'description', 'category_id', 'day_of_month'];

// Sem receita: cadastro novo começa no dia 5, o dia mais comum de salário.
const DEFAULT_DAY = 5;

type Props = {
  // Sem receita: cadastro novo (Agenda). Com receita: edição (ou só leitura, se a pessoa não pode alterar).
  income?: RecurringIncome;
};

/**
 * Valor, descrição, categoria, dia e "só eu vejo" de uma receita fixa. Na edição, também "Parar de
 * repetir"; as mudanças valem a partir do próximo lançamento e os meses já lançados não mudam.
 */
export function RecurringIncomeForm({ income }: Props) {
  const readOnly = income !== undefined && !income.can_edit;

  const [amount, setAmount] = useState(income?.amount_cents ?? 0);
  const [description, setDescription] = useState(income?.description ?? '');
  const [categoryId, setCategoryId] = useState<number | null>(income?.category.id ?? null);
  const [day, setDay] = useState(income?.day_of_month ?? DEFAULT_DAY);
  const [isPrivate, setIsPrivate] = useState(income?.visibility === 'private');

  const today = todayISO();
  const first = firstIncomeDate(day, today);

  const save = useMutation({
    mutationFn: (): Promise<unknown> => {
      const input = {
        amount_cents: amount,
        description,
        category_id: categoryId ?? 0,
        day_of_month: day,
        visibility: isPrivate ? ('private' as const) : ('household' as const),
      };

      return income ? updateRecurringIncome(income.id, input) : createRecurringIncome(input);
    },
    onSuccess: closeAndRefresh,
  });

  const stop = useMutation({ mutationFn: () => stopRecurringIncome(income?.id ?? 0), onSuccess: closeAndRefresh });

  const failure = save.error ?? stop.error;
  const error = failure instanceof ApiError ? failure : null;

  async function confirmStop() {
    const confirmed = await confirm({
      title: `Parar de repetir ${income?.description ?? 'a receita'}?`,
      message: 'Os próximos meses não são mais lançados. O que já entrou continua nos lançamentos e nos totais.',
      confirmLabel: 'Parar de repetir',
      destructive: true,
    });

    if (confirmed) {
      stop.mutate();
    }
  }

  // Só leitura: sem o botão principal (e sem rodapé).
  const submit = readOnly ? undefined : (
    <Button
      title={income ? 'Salvar' : 'Cadastrar receita'}
      icon={income ? <Check size={18} color={colors.canvas} strokeWidth={1.6} /> : <Plus size={18} color={colors.canvas} strokeWidth={1.6} />}
      loading={save.isPending}
      disabled={amount === 0}
      onPress={() => save.mutate()}
    />
  );

  return (
    <Form submit={submit} error={error?.messageOutside(VISIBLE_FIELDS)}>
      <Notice>{noticeText(income, readOnly, first, today)}</Notice>

      <MoneyInput
        value={amount}
        onChange={setAmount}
        autoFocus={income === undefined}
        editable={!readOnly}
        error={error?.field('amount_cents')}
      />

      <TextField
        label="Descrição"
        value={description}
        onChangeText={setDescription}
        placeholder="Ex.: Salário"
        maxLength={120}
        editable={!readOnly}
        error={error?.field('description')}
      />

      <Field label="Categoria" error={error?.field('category_id')}>
        <CategoryPicker kind="income" value={categoryId} onChange={setCategoryId} disabled={readOnly} />
      </Field>

      <Field
        label="Cai todo dia"
        error={error?.field('day_of_month')}
        hint={day > 28 ? 'Nos meses mais curtos, cai no último dia do mês.' : undefined}
      >
        {/* Só leitura: a grade aparece, mas não responde ao toque. */}
        <View style={readOnly && styles.locked}>
          <DueDayPicker value={day} onChange={setDay} />
        </View>
      </Field>

      <ToggleRow
        label="Só eu vejo"
        description="Os outros membros da casa não veem esta receita, nem os lançamentos dela."
        value={isPrivate}
        onChange={setIsPrivate}
        disabled={readOnly}
      />

      {income && !readOnly ? (
        <Button
          title="Parar de repetir"
          variant="dangerLink"
          icon={<RepeatOff size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={stop.isPending}
          onPress={confirmStop}
        />
      ) : null}
    </Form>
  );
}

/**
 * O aviso do topo. Cadastro novo: quando cai a primeira (e que hoje já entra ao salvar). Edição:
 * quando é o próximo lançamento. Só leitura: quem pode alterar.
 */
function noticeText(income: RecurringIncome | undefined, readOnly: boolean, first: string, today: string): string {
  if (!income) {
    return first === today
      ? 'O dia é hoje: a receita de hoje já entra ao cadastrar, e as próximas caem sozinhas todo mês.'
      : `Primeiro lançamento em ${shortDate(first)}. Depois, cai sozinha todo mês nesse dia.`;
  }

  if (readOnly) {
    return `Cadastrada por ${income.created_by?.name ?? 'outra pessoa'}. Só quem cadastrou ou o dono da casa pode alterar.`;
  }

  return `Próximo lançamento em ${shortDate(income.next_on)}. As mudanças valem a partir dele; os meses já lançados ficam como estão.`;
}

const styles = StyleSheet.create({
  locked: { pointerEvents: 'none' },
});
