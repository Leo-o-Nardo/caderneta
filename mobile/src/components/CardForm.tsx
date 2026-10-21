import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Archive, Check, Plus } from 'lucide-react-native';
import { useState } from 'react';

import { archiveCard, createCard, updateCard } from '@/api/cards';
import { invalidateFinance } from '@/api/keys';
import type { Card } from '@/api/types';
import { Button, Field, Form, Text, TextField, ToggleRow } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { closeAndRefresh } from '@/lib/navigation';
import { confirm } from '@/stores/confirm';
import { colors } from '@/theme/tokens';

import { ColorPicker } from './ColorPicker';
import { DueDayPicker } from './DueDayPicker';
import { MoneyInput } from './MoneyInput';

// As cores de cartão, oito dos tons de pigmento do guia: Ameixa, Ardósia, Louro, Açafrão, Canela,
// Telha, Rosa-argila e Pedra.
const CARD_COLORS = ['#7E5490', '#4A6A9A', '#4F7F4F', '#B8902F', '#C27A3A', '#B9553A', '#B5677E', '#7D786D'];

const VISIBLE_FIELDS = ['name', 'closing_day', 'due_day', 'limit_cents', 'color'];

type Props = {
  // Sem cartão: novo (em nome de quem está usando o app). Com cartão: edição (só do próprio).
  card?: Card;
};

export function CardForm({ card }: Props) {
  const [name, setName] = useState(card?.name ?? '');
  const [closingDay, setClosingDay] = useState(card?.closing_day ?? 3);
  const [dueDay, setDueDay] = useState(card?.due_day ?? 10);
  const [limit, setLimit] = useState(card?.limit_cents ?? 0);
  const [color, setColor] = useState(card?.color ?? CARD_COLORS[0]);
  const [isDefault, setIsDefault] = useState(card?.is_default ?? false);

  const save = useMutation({
    mutationFn: (): Promise<unknown> => {
      const input = {
        name,
        closing_day: closingDay,
        due_day: dueDay,
        limit_cents: limit > 0 ? limit : null,
        color,
        is_default: isDefault,
      };

      return card ? updateCard(card.id, input) : createCard(input);
    },
    onSuccess: closeAndRefresh,
  });

  const archive = useMutation({
    mutationFn: () => archiveCard(card?.id ?? 0),
    onSuccess: () => {
      // A fatura do cartão arquivado não faz mais sentido: volta direto para a Agenda.
      router.dismissTo('/agenda');
      invalidateFinance();
    },
  });

  const error = save.error instanceof ApiError ? save.error : null;
  const generalError = error?.messageOutside(VISIBLE_FIELDS) ?? (archive.error ? archive.error.message : null);

  async function confirmArchive() {
    const confirmed = await confirm({
      title: `Arquivar o ${card?.name ?? 'cartão'}?`,
      message:
        'Ele sai da Agenda e das formas de pagamento. As compras já lançadas nele, com as parcelas que faltam, ' +
        'continuam nos lançamentos e nos totais.',
      confirmLabel: 'Arquivar cartão',
      destructive: true,
    });

    if (confirmed) {
      archive.mutate();
    }
  }

  return (
    <Form
      error={generalError}
      submit={
        <Button
          title={card ? 'Salvar' : 'Cadastrar cartão'}
          icon={card ? <Check size={18} color={colors.canvas} strokeWidth={1.6} /> : <Plus size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={save.isPending}
          onPress={() => save.mutate()}
        />
      }
    >
      <TextField label="Nome" value={name} onChangeText={setName} placeholder="Ex.: Nubank" maxLength={40} error={error?.field('name')} />

      <Field
        label="Fecha todo dia"
        error={error?.field('closing_day')}
        hint="Compras desse dia em diante vão para a fatura seguinte: é o melhor dia para comprar."
      >
        <DueDayPicker value={closingDay} onChange={setClosingDay} />
      </Field>

      <Field label="Vence todo dia" error={error?.field('due_day')}>
        <DueDayPicker value={dueDay} onChange={setDueDay} />
      </Field>

      <Field label="Limite (opcional)" error={error?.field('limit_cents')} hint="Para acompanhar quanto do limite já está usado.">
        <MoneyInput value={limit} onChange={setLimit} />
      </Field>

      <Field label="Cor" error={error?.field('color')}>
        <ColorPicker value={color} onChange={setColor} options={CARD_COLORS} />
      </Field>

      {card?.is_default ? (
        <Text variant="caption" tone="muted">
          Este é o seu cartão padrão: o usado quando você diz só “no cartão”.
        </Text>
      ) : (
        <ToggleRow
          label="Cartão padrão"
          description="Usado quando você disser só “no cartão”."
          value={isDefault}
          onChange={setIsDefault}
        />
      )}

      {card ? (
        <Button
          title="Arquivar cartão"
          variant="dangerLink"
          icon={<Archive size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={archive.isPending}
          onPress={confirmArchive}
        />
      ) : null}
    </Form>
  );
}
