import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Check, Trash2 } from 'lucide-react-native';
import { useState } from 'react';

import { deleteListItem, invalidateLists, updateListItem } from '@/api/lists';
import type { ListItem, ListKind } from '@/api/types';
import { Button, Field, Form, TextField, ToggleRow } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { todayISO } from '@/lib/dates';
import { confirm } from '@/stores/confirm';
import { colors } from '@/theme/tokens';

import { DateField } from './DateField';
import { MoneyInput } from './MoneyInput';

const VISIBLE_FIELDS = ['text', 'quantity', 'note', 'price_cents', 'url', 'due_on'];

type Props = {
  item: ListItem;
  kind: ListKind;
  // O nome da lista, para a folha dizer de onde o item sai.
  listName: string;
};

/**
 * Editar um item. Os campos seguem o tipo da lista: quantidade nas compras, preço e link nos
 * desejos, data nas coisas a fazer; a lista "Outra" mostra todos.
 */
export function ListItemForm({ item, kind, listName }: Props) {
  const shows = {
    quantity: kind === 'shopping' || kind === 'custom',
    price: kind === 'wishlist' || kind === 'custom',
    date: kind === 'todo' || kind === 'custom',
  };

  const [text, setText] = useState(item.text);
  const [quantity, setQuantity] = useState(item.quantity ?? '');
  const [note, setNote] = useState(item.note ?? '');
  const [price, setPrice] = useState(item.price_cents ?? 0);
  const [url, setUrl] = useState(item.url ?? '');
  const [hasDate, setHasDate] = useState(item.due_on !== null);
  const [date, setDate] = useState(item.due_on ?? todayISO());

  const done = () => {
    router.back();
    invalidateLists();
  };

  const save = useMutation({
    mutationFn: () =>
      updateListItem(item.id, {
        text,
        note: note.trim() || null,
        ...(shows.quantity ? { quantity: quantity.trim() || null } : {}),
        ...(shows.price ? { price_cents: price > 0 ? price : null, url: url.trim() || null } : {}),
        ...(shows.date ? { due_on: hasDate ? date : null } : {}),
      }),
    onSuccess: done,
  });

  const remove = useMutation({ mutationFn: () => deleteListItem(item.id), onSuccess: done });

  const failure = save.error ?? remove.error;
  const error = failure instanceof ApiError ? failure : null;

  async function confirmDelete() {
    const confirmed = await confirm({
      title: `Tirar ${item.text} da lista?`,
      message: `O item sai de ${listName}. Para ter de novo, é só adicionar.`,
      confirmLabel: 'Tirar da lista',
      destructive: true,
    });

    if (confirmed) {
      remove.mutate();
    }
  }

  return (
    <Form
      error={error?.messageOutside(VISIBLE_FIELDS)}
      submit={
        <Button
          title="Salvar"
          icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={save.isPending}
          onPress={() => save.mutate()}
        />
      }
    >
      <TextField label="Item" value={text} onChangeText={setText} maxLength={120} error={error?.field('text')} />

      {shows.quantity ? (
        <TextField
          label="Quantidade"
          value={quantity}
          onChangeText={setQuantity}
          placeholder="Ex.: 2, 1 kg, 1 dúzia"
          maxLength={20}
          error={error?.field('quantity')}
        />
      ) : null}

      {shows.price ? (
        <>
          <Field label="Preço (opcional)" error={error?.field('price_cents')}>
            <MoneyInput value={price} onChange={setPrice} />
          </Field>
          <TextField
            label="Link (opcional)"
            value={url}
            onChangeText={setUrl}
            placeholder="https://"
            autoCapitalize="none"
            keyboardType="url"
            maxLength={500}
            error={error?.field('url')}
          />
        </>
      ) : null}

      {shows.date ? (
        <>
          <ToggleRow label="Tem data" value={hasDate} onChange={setHasDate} />
          {hasDate ? <DateField label="Até" value={date} onChange={setDate} direction="future" error={error?.field('due_on')} /> : null}
        </>
      ) : null}

      <TextField label="Observação (opcional)" value={note} onChangeText={setNote} maxLength={200} error={error?.field('note')} />

      <Button
        title="Tirar da lista"
        variant="dangerLink"
        icon={<Trash2 size={18} color={colors.danger} strokeWidth={1.6} />}
        loading={remove.isPending}
        onPress={confirmDelete}
      />
    </Form>
  );
}
