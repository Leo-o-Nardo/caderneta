import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Archive, Check, Plus } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { archiveCategory, createCategory, invalidateCategories, updateCategory } from '@/api/categories';
import type { Category, CategoryKind } from '@/api/types';
import { Button, Field, Form, SegmentedControl, Text, TextField } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { CATEGORY_COLORS } from '@/lib/categories';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

import { CategoryIcon } from './CategoryIcon';
import { ColorPicker } from './ColorPicker';
import { IconPicker } from './IconPicker';

const KIND_OPTIONS: { value: CategoryKind; label: string }[] = [
  { value: 'expense', label: 'Despesa' },
  { value: 'income', label: 'Receita' },
];

const VISIBLE_FIELDS = ['kind', 'name', 'icon', 'color'];

type Props = {
  // Sem categoria: nova. Com categoria: edição.
  category?: Category;
  // O tipo inicial de uma categoria nova (a aba que estava aberta na lista).
  kind?: CategoryKind;
};

/**
 * Nome, ícone e cor, com a prévia no topo: a linha do jeito que vai aparecer nos lançamentos. O
 * tipo só é escolhido ao criar: depois a API não deixa mudar.
 */
export function CategoryForm({ category, kind: initialKind = 'expense' }: Props) {
  const [kind, setKind] = useState<CategoryKind>(category?.kind ?? initialKind);
  const [name, setName] = useState(category?.name ?? '');
  const [icon, setIcon] = useState(category?.icon ?? 'dots');
  const [color, setColor] = useState<string>(category?.color ?? CATEGORY_COLORS[0]);

  const done = () => {
    router.back();
    invalidateCategories();
  };

  const save = useMutation({
    mutationFn: (): Promise<unknown> => {
      const input = { name, icon, color };

      return category ? updateCategory(category.id, input) : createCategory({ ...input, kind });
    },
    onSuccess: done,
  });

  const archive = useMutation({ mutationFn: () => archiveCategory(category?.id ?? 0), onSuccess: done });

  const failure = save.error ?? archive.error;
  const error = failure instanceof ApiError ? failure : null;

  async function confirmArchive() {
    const confirmed = await confirm({
      title: `Arquivar ${category?.name ?? 'a categoria'}?`,
      message:
        'Ela sai da hora de lançar. Os lançamentos antigos continuam com ela, nas listas e nos totais.' +
        (category?.kind === 'expense' ? ' Se ela tiver um limite de orçamento, ele sai junto.' : ''),
      confirmLabel: 'Arquivar categoria',
      destructive: true,
    });

    if (confirmed) {
      archive.mutate();
    }
  }

  return (
    <Form
      error={error?.messageOutside(VISIBLE_FIELDS)}
      submit={
        <Button
          title={category ? 'Salvar' : 'Criar categoria'}
          icon={
            category ? <Check size={18} color={colors.canvas} strokeWidth={1.6} /> : <Plus size={18} color={colors.canvas} strokeWidth={1.6} />
          }
          loading={save.isPending}
          onPress={() => save.mutate()}
        />
      }
    >
      {/* A prévia: a linha como nos lançamentos, entre duas pautas. */}
      <View style={styles.preview} accessibilityLabel={`Prévia: ${name.trim() || 'Nova categoria'}`}>
        <CategoryIcon icon={icon} color={color} />
        <View style={styles.previewTexts}>
          <Text variant="strong" numberOfLines={1}>
            {name.trim() || 'Nova categoria'}
          </Text>
          <Text variant="caption" tone="muted">
            {kind === 'expense' ? 'Despesa' : 'Receita'} · assim aparece nos lançamentos
          </Text>
        </View>
      </View>

      <TextField
        label="Nome"
        value={name}
        onChangeText={setName}
        placeholder={kind === 'expense' ? 'Ex.: Viagem, Farmácia' : 'Ex.: Freela, Aluguel recebido'}
        maxLength={40}
        autoFocus={!category}
        error={error?.field('name')}
      />

      {category ? null : (
        <Field label="Tipo" error={error?.field('kind')}>
          <SegmentedControl options={KIND_OPTIONS} value={kind} onChange={setKind} />
        </Field>
      )}

      <Field label="Ícone" error={error?.field('icon')}>
        <IconPicker value={icon} color={color} onChange={setIcon} />
      </Field>

      <Field label="Cor" error={error?.field('color')}>
        <ColorPicker value={color} onChange={setColor} />
      </Field>

      {category ? (
        <Button
          title="Arquivar categoria"
          variant="dangerLink"
          icon={<Archive size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={archive.isPending}
          onPress={confirmArchive}
        />
      ) : null}
    </Form>
  );
}

const styles = StyleSheet.create({
  preview: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: colors.line,
  },
  previewTexts: { flex: 1, gap: space.xs },
});
