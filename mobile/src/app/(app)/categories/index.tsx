import { useMutation } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { router, useLocalSearchParams } from 'expo-router';
import { ArrowUpDown, ArrowUpToLine, Check, ChevronDown, ChevronRight, ChevronUp, Plus, type LucideIcon } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';

import { useBudgets } from '@/api/budgets';
import { invalidateCategories, reorderCategories, useCategories } from '@/api/categories';
import { updateOnboarding } from '@/api/onboarding';
import type { Category, CategoryKind } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { Button, Header, IconButton, Screen, SegmentedControl, Text } from '@/components/ui';
import { toMonth } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { colors, radius, space } from '@/theme/tokens';

const KIND_OPTIONS: { value: CategoryKind; label: string }[] = [
  { value: 'expense', label: 'Despesas' },
  { value: 'income', label: 'Receitas' },
];

/**
 * As categorias da casa, separadas em despesas e receitas. Tocar abre a edição. No modo
 * "Organizar", as setas mudam a ordem, que vale também na grade dos lançamentos (as 7 primeiras).
 */
export default function CategoriesScreen() {
  const [kind, setKind] = useState<CategoryKind>('expense');
  // A ordem sendo montada no modo organizar; null fora dele.
  const [order, setOrder] = useState<Category[] | null>(null);
  const categories = useCategories(kind);
  const budgets = useBudgets(toMonth(new Date()));
  // O limite de cada categoria de despesa, para mostrar na linha.
  const limits = new Map(budgets.data?.map((budget) => [budget.category.id, budget.limit_cents] as const));

  // Aberta pelos primeiros passos ("Conferir as categorias"): no fim da lista, "Estão boas".
  const reviewing = useLocalSearchParams<{ review?: string }>().review === '1';
  const review = useMutation({
    mutationFn: () => updateOnboarding({ categories_reviewed: true }),
    onSuccess: () => router.back(),
    onError: (error) => Alert.alert('Não deu para salvar', error.message),
  });

  const organizing = order !== null;
  const rows = order ?? categories.data ?? [];

  const save = useMutation({
    mutationFn: () => reorderCategories(kind, order ?? []),
    onSuccess: () => {
      setOrder(null);
      invalidateCategories();
    },
    onError: (error) => {
      setOrder(null);
      Alert.alert('Não deu para salvar a ordem', error.message);
    },
  });

  /** Tira a categoria de "from" e coloca em "to" (0 é o topo). */
  const move = (from: number, to: number) => {
    Haptics.selectionAsync();
    setOrder((current) => {
      const next = [...(current ?? [])];
      const [item] = next.splice(from, 1);
      next.splice(to, 0, item);

      return next;
    });
  };

  const headerRight = organizing ? (
    <Button title="Pronto" variant="ghost" loading={save.isPending} onPress={() => save.mutate()} />
  ) : (
    <View style={styles.headerActions}>
      <IconButton
        icon={ArrowUpDown}
        label="Organizar a ordem"
        edge={false}
        onPress={() => categories.data && setOrder(categories.data)}
      />
      <IconButton icon={Plus} label="Nova categoria" onPress={() => router.push({ pathname: '/categories/new', params: { kind } })} />
    </View>
  );

  return (
    <Screen scroll>
      <Header title={organizing ? 'Organizar' : 'Categorias'} right={headerRight} />

      <SegmentedControl options={KIND_OPTIONS} value={kind} onChange={setKind} disabled={organizing} />

      {categories.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : categories.isError ? (
        <Text tone="danger" style={styles.status}>
          {categories.error.message}
        </Text>
      ) : (
        <View style={styles.list}>
          {organizing ? (
            <Text variant="caption" tone="muted" style={styles.hint}>
              As 7 primeiras aparecem na grade, na hora de lançar. Toque em Pronto para salvar.
            </Text>
          ) : null}

          {rows.map((category, index) =>
            organizing ? (
              <View key={category.id} style={styles.row}>
                <CategoryIcon icon={category.icon} color={category.color} />
                <Text variant="strong" style={styles.texts} numberOfLines={1}>
                  {category.name}
                </Text>
                <MoveButton icon={ArrowUpToLine} label={`${category.name} para o topo`} disabled={index === 0} onPress={() => move(index, 0)} />
                <MoveButton icon={ChevronUp} label={`Subir ${category.name}`} disabled={index === 0} onPress={() => move(index, index - 1)} />
                <MoveButton
                  icon={ChevronDown}
                  label={`Descer ${category.name}`}
                  disabled={index === rows.length - 1}
                  onPress={() => move(index, index + 1)}
                />
              </View>
            ) : (
              <Pressable
                key={category.id}
                onPress={() => router.push({ pathname: '/categories/[id]', params: { id: String(category.id) } })}
                style={({ pressed }) => [styles.row, pressed && styles.pressed]}
                accessibilityRole="button"
              >
                <CategoryIcon icon={category.icon} color={category.color} />
                <View style={styles.texts}>
                  <Text variant="strong">{category.name}</Text>
                  {limits.get(category.id) ? (
                    <Text variant="caption" tone="muted">
                      Limite de {formatCents(limits.get(category.id) ?? 0)} por mês
                    </Text>
                  ) : null}
                </View>
                <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
              </Pressable>
            ),
          )}

          {organizing ? (
            <Button title="Descartar mudanças" variant="ghost" disabled={save.isPending} onPress={() => setOrder(null)} style={styles.after} />
          ) : (
            <Text variant="caption" tone="muted" style={styles.after}>
              Arquivar uma categoria tira ela da hora de lançar, mas os lançamentos antigos continuam com ela.
            </Text>
          )}

          {/* Vindo dos primeiros passos: conferir as categorias termina aqui. */}
          {reviewing && !organizing ? (
            <Button
              title="Estão boas"
              icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
              loading={review.isPending}
              onPress={() => review.mutate()}
              style={styles.after}
            />
          ) : null}
        </View>
      )}
    </Screen>
  );
}

type MoveButtonProps = {
  icon: LucideIcon;
  label: string;
  disabled: boolean;
  onPress: () => void;
};

/** Uma seta do modo organizar, com os 44 de toque do guia. */
function MoveButton({ icon: Icon, label, disabled, onPress }: MoveButtonProps) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={({ pressed }) => [styles.move, pressed && styles.pressed, disabled && styles.moveDisabled]}
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled }}
    >
      <Icon size={20} color={colors.ink} strokeWidth={1.6} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  headerActions: { flexDirection: 'row', alignItems: 'center' },
  list: { marginTop: space.lg },
  hint: { marginBottom: space.sm },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    minHeight: 56,
    paddingVertical: space.sm,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  pressed: { backgroundColor: colors.pressed },
  texts: { flex: 1, gap: space.xs },
  move: { width: 44, height: 44, borderRadius: radius.md, alignItems: 'center', justifyContent: 'center' },
  moveDisabled: { opacity: 0.3 },
  after: { marginTop: space.lg },
});
