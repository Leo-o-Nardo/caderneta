import { useMutation } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { router } from 'expo-router';
import { ChevronDown, ChevronRight, Ellipsis } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { addListItems, clearCheckedItems, invalidateLists, useListItems, useToggleItem } from '@/api/lists';
import type { HouseholdList, ListItem } from '@/api/types';
import { IconButton, Tag, Text, TextLink } from '@/components/ui';
import { ApiError } from '@/lib/apiError';
import { LIST_KINDS, parseItems } from '@/lib/lists';
import { formatCents } from '@/lib/money';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

import { AddItemsBar } from './AddItemsBar';
import { ListItemRow } from './ListItemRow';

type Props = {
  list: HouseholdList;
};

/**
 * Uma lista aberta: o campo de adicionar, os itens em aberto e, embaixo, os já pegos. A linha
 * inteira do item é o botão de marcar, para usar com o polegar no mercado.
 */
export function ListView({ list }: Props) {
  const items = useListItems(list.id);
  const toggle = useToggleItem(list.id);
  const [text, setText] = useState('');
  const [notice, setNotice] = useState<string | null>(null);
  const [showPicked, setShowPicked] = useState(true);
  const kind = LIST_KINDS[list.kind];

  const add = useMutation({
    mutationFn: (texts: string[]) => addListItems(list.id, texts.map((item) => ({ text: item }))),
    onSuccess: ({ existing }) => {
      setText('');
      setNotice(
        existing.length > 0
          ? `${existing.map((item) => item.text).join(', ')} já ${existing.length === 1 ? 'estava' : 'estavam'} na lista.`
          : null,
      );
      invalidateLists();
    },
  });

  const clear = useMutation({ mutationFn: () => clearCheckedItems(list.id), onSuccess: () => invalidateLists() });

  const open = items.data?.filter((item) => !item.is_checked) ?? [];
  const picked = items.data?.filter((item) => item.is_checked) ?? [];
  const failure = add.error ?? toggle.error ?? clear.error;

  function onToggle(item: ListItem) {
    Haptics.selectionAsync();
    toggle.mutate({ id: item.id, checked: !item.is_checked });
  }

  const openEditor = (item: ListItem) =>
    router.push({ pathname: '/list-items/[id]', params: { id: String(item.id), list_id: String(list.id) } });

  async function confirmClear() {
    const confirmed = await confirm({
      title: 'Limpar os itens pegos?',
      message: `${picked.length} ${picked.length === 1 ? 'item sai' : 'itens saem'} da lista. Os que faltam pegar continuam.`,
      confirmLabel: 'Limpar pegos',
      destructive: true,
    });

    if (confirmed) {
      clear.mutate();
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.titleRow}>
        <View style={styles.flex}>
          <View style={styles.titleLine}>
            <Text variant="title" style={styles.shrink}>
              {list.name}
            </Text>
            {list.visibility === 'private' ? <Tag label="só você" /> : null}
          </View>
          <Text variant="caption" tone="muted">
            {kind.label}
            {list.is_default ? ' · lista padrão' : ''}
            {list.kind === 'wishlist' && list.open_total_cents > 0 ? ` · ${formatCents(list.open_total_cents)} em aberto` : ''}
          </Text>
        </View>
        <IconButton
          icon={Ellipsis}
          label="Opções da lista"
          onPress={() => router.push({ pathname: '/lists/[id]/edit', params: { id: String(list.id) } })}
        />
      </View>

      <AddItemsBar
        value={text}
        onChange={(value) => {
          setText(value);
          setNotice(null);
        }}
        onSubmit={() => add.mutate(parseItems(text))}
        placeholder={kind.placeholder}
        loading={add.isPending}
      />
      {failure instanceof ApiError ? (
        <Text variant="caption" tone="danger">
          {failure.message}
        </Text>
      ) : notice ? (
        <Text variant="caption" tone="muted">
          {notice}
        </Text>
      ) : null}

      <ScrollView
        style={styles.flex}
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={items.isRefetching}
            onRefresh={() => items.refetch()}
            tintColor={colors.accent}
            colors={[colors.accent]}
            progressBackgroundColor={colors.surface}
          />
        }
      >
        {items.isPending ? (
          <ActivityIndicator color={colors.accent} style={styles.status} />
        ) : (
          <>
            {open.length > 0 ? (
              <View style={styles.items}>
                {open.map((item) => (
                  <ListItemRow key={item.id} item={item} kind={list.kind} onToggle={() => onToggle(item)} onEdit={() => openEditor(item)} />
                ))}
              </View>
            ) : (
              <Text tone="muted" style={styles.empty}>
                {picked.length > 0 ? 'Tudo pego!' : 'Lista vazia. Adicione itens no campo acima.'}
              </Text>
            )}

            {open.length > 0 && picked.length === 0 ? (
              <Text variant="caption" tone="muted" style={styles.hint}>
                Toque para marcar · segure para editar
              </Text>
            ) : null}

            {picked.length > 0 ? (
              <View style={styles.picked}>
                {/* O título dos já pegos é uma seção que abre e fecha, com o "Limpar" do lado. */}
                <View style={styles.pickedHeader}>
                  <Pressable
                    onPress={() => setShowPicked((value) => !value)}
                    style={styles.pickedToggle}
                    hitSlop={8}
                    accessibilityRole="button"
                    accessibilityState={{ expanded: showPicked }}
                  >
                    {showPicked ? (
                      <ChevronDown size={18} color={colors.muted} strokeWidth={1.6} />
                    ) : (
                      <ChevronRight size={18} color={colors.muted} strokeWidth={1.6} />
                    )}
                    <Text variant="subtitle">
                      Já pegos
                      <Text variant="caption" tone="muted">{`  ·  ${picked.length}`}</Text>
                    </Text>
                  </Pressable>
                  <TextLink label="Limpar" size="sm" onPress={confirmClear} />
                </View>

                {showPicked
                  ? picked.map((item) => (
                      <ListItemRow key={item.id} item={item} kind={list.kind} onToggle={() => onToggle(item)} onEdit={() => openEditor(item)} />
                    ))
                  : null}
              </View>
            ) : null}
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, gap: space.md },
  flex: { flex: 1 },
  shrink: { flexShrink: 1 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: space.md },
  titleLine: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  content: { paddingBottom: space.xxl },
  status: { marginTop: space.xxl },
  items: { borderTopWidth: 1, borderTopColor: colors.line },
  empty: { marginTop: space.xl },
  hint: { marginTop: space.md },
  picked: { marginTop: space.xxl },
  pickedHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingBottom: space.sm,
    borderBottomWidth: 1,
    borderBottomColor: colors.ink,
  },
  pickedToggle: { flexDirection: 'row', alignItems: 'center', gap: space.xs },
});
