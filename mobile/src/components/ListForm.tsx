import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Check, Plus, Trash2 } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { createList, deleteList, invalidateLists, updateList } from '@/api/lists';
import type { HouseholdList, ListKind } from '@/api/types';
import { Button, Chip, Field, Form, Text, TextField, ToggleRow } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { LIST_KINDS } from '@/lib/lists';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

const KINDS: ListKind[] = ['shopping', 'wishlist', 'todo', 'custom'];

const VISIBLE_FIELDS = ['name', 'kind', 'visibility', 'is_default'];

type Props = {
  // Sem lista: nova. Com lista: edição.
  list?: HouseholdList;
};

/** Nome, tipo, "só eu vejo" e lista padrão. A padrão precisa ser da casa e não pode ser apagada. */
export function ListForm({ list }: Props) {
  const [name, setName] = useState(list?.name ?? '');
  const [kind, setKind] = useState<ListKind>(list?.kind ?? 'custom');
  const [isPrivate, setIsPrivate] = useState(list?.visibility === 'private');
  const [isDefault, setIsDefault] = useState(list?.is_default ?? false);

  const done = () => {
    router.back();
    invalidateLists();
  };

  const save = useMutation({
    mutationFn: (): Promise<unknown> => {
      const input = {
        name,
        kind,
        visibility: isPrivate ? ('private' as const) : ('household' as const),
        // Só "ligar" faz sentido: para deixar de ser a padrão, outra lista vira a padrão.
        ...(isDefault && !list?.is_default ? { is_default: true } : {}),
      };

      return list ? updateList(list.id, input) : createList(input);
    },
    onSuccess: done,
  });

  const remove = useMutation({ mutationFn: () => deleteList(list?.id ?? 0), onSuccess: done });

  const failure = save.error ?? remove.error;
  const error = failure instanceof ApiError ? failure : null;

  // A folha diz quantos itens vão junto.
  async function confirmDelete() {
    if (!list) {
      return;
    }

    const count = list.items_count;
    const confirmed = await confirm({
      title: `Apagar a lista ${list.name}?`,
      message: count === 0 ? 'Ela está vazia.' : `Ela é apagada com ${count === 1 ? 'o item que está nela' : `os ${count} itens`}.`,
      confirmLabel: 'Apagar lista',
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
          title={list ? 'Salvar' : 'Criar lista'}
          icon={list ? <Check size={18} color={colors.canvas} strokeWidth={1.6} /> : <Plus size={18} color={colors.canvas} strokeWidth={1.6} />}
          loading={save.isPending}
          onPress={() => save.mutate()}
        />
      }
    >
      <TextField
        label="Nome"
        value={name}
        onChangeText={setName}
        placeholder="Ex.: Viagem, Farmácia"
        maxLength={40}
        autoFocus={!list}
        error={error?.field('name')}
      />

      <Field label="Tipo" error={error?.field('kind')} hint="O tipo muda o que aparece nos itens: quantidade, preço ou data.">
        <View style={styles.wrap}>
          {KINDS.map((option) => (
            <Chip key={option} label={LIST_KINDS[option].label} selected={kind === option} onPress={() => setKind(option)} />
          ))}
        </View>
      </Field>

      <ToggleRow
        label="Só eu vejo"
        description="Os outros membros da casa não veem esta lista."
        value={isPrivate}
        onChange={(value) => {
          setIsPrivate(value);
          if (value) {
            setIsDefault(false);
          }
        }}
        disabled={list?.is_default}
      />

      {list?.is_default ? (
        <Text variant="caption" tone="muted">
          Esta é a lista padrão: a usada quando alguém diz só “coloca na lista”. Para trocar, marque outra lista como padrão.
        </Text>
      ) : (
        <ToggleRow
          label="Lista padrão"
          description={isPrivate ? 'Uma lista privada não pode ser a padrão.' : 'A usada quando alguém diz só “coloca na lista”.'}
          value={isDefault}
          onChange={setIsDefault}
          disabled={isPrivate}
        />
      )}

      {list && !list.is_default ? (
        <Button
          title="Apagar lista"
          variant="dangerLink"
          icon={<Trash2 size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={remove.isPending}
          onPress={confirmDelete}
        />
      ) : null}
    </Form>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: space.sm },
});
