import { router } from 'expo-router';
import { LayoutGrid } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, useWindowDimensions, View } from 'react-native';

import { useCategories } from '@/api/categories';
import type { Category, CategoryKind } from '@/api/types';
import { BottomSheet, Text, TextLink } from '@/components/ui';
import { gridCategories } from '@/lib/categories';
import { colors, radius, space } from '@/theme/tokens';

import { CategoryIcon } from './CategoryIcon';

type Props = {
  kind: CategoryKind;
  value: number | null;
  onChange: (id: number) => void;
  disabled?: boolean;
};

/**
 * A escolha da categoria (guia 1.3): uma grade de 4 colunas com as primeiras categorias, na ordem
 * de Configurações › Categorias, e "Todas", que abre a folha com a grade completa. A escolhida
 * sempre aparece na grade.
 */
export function CategoryPicker({ kind, value, onChange, disabled = false }: Props) {
  const categories = useCategories(kind);
  const [showAll, setShowAll] = useState(false);
  const { height } = useWindowDimensions();
  const all = categories.data ?? [];
  const { shown, hasMore } = gridCategories(all, value);

  function pick(id: number) {
    onChange(id);
    setShowAll(false);
  }

  function createCategory() {
    setShowAll(false);
    router.push({ pathname: '/categories/new', params: { kind } });
  }

  return (
    <>
      <View style={styles.grid}>
        {shown.map((category) => (
          <CategoryCell
            key={category.id}
            category={category}
            selected={category.id === value}
            disabled={disabled}
            onPress={() => onChange(category.id)}
          />
        ))}
        {hasMore ? <MoreCell disabled={disabled} onPress={() => setShowAll(true)} /> : null}
      </View>

      <BottomSheet visible={showAll} onClose={() => setShowAll(false)}>
        <View style={styles.sheetHeader}>
          <Text variant="subtitle" accessibilityRole="header">
            Categoria
          </Text>
          <TextLink label="Nova categoria" size="sm" onPress={createCategory} />
        </View>
        {/* Com muitas categorias, a grade rola dentro da folha. */}
        <ScrollView style={{ maxHeight: height * 0.6 }} showsVerticalScrollIndicator={false}>
          <View style={styles.grid}>
            {all.map((category) => (
              <CategoryCell
                key={category.id}
                category={category}
                selected={category.id === value}
                onPress={() => pick(category.id)}
              />
            ))}
          </View>
        </ScrollView>
      </BottomSheet>
    </>
  );
}

type CellProps = {
  category: Category;
  selected: boolean;
  disabled?: boolean;
  onPress: () => void;
};

/** Uma casa da grade: o ícone colorido em cima e o nome embaixo; a escolhida em Musgo. */
function CategoryCell({ category, selected, disabled = false, onPress }: CellProps) {
  return (
    <View style={styles.slot}>
      <Pressable
        onPress={onPress}
        disabled={disabled}
        style={({ pressed }) => [styles.cell, pressed && styles.pressed, selected && styles.selected, disabled && !selected && styles.disabled]}
        accessibilityRole="button"
        accessibilityLabel={category.name}
        accessibilityState={{ selected, disabled }}
      >
        <CategoryIcon icon={category.icon} color={category.color} />
        <Text variant="label" tone={selected ? 'accent' : 'ink'} numberOfLines={2} style={styles.name}>
          {category.name}
        </Text>
      </Pressable>
    </View>
  );
}

/** A última casa: "Todas", que abre a folha com a grade completa. */
function MoreCell({ disabled, onPress }: { disabled: boolean; onPress: () => void }) {
  return (
    <View style={styles.slot}>
      <Pressable
        onPress={onPress}
        disabled={disabled}
        style={({ pressed }) => [styles.cell, pressed && styles.pressed, disabled && styles.disabled]}
        accessibilityRole="button"
        accessibilityLabel="Todas as categorias"
      >
        <LayoutGrid size={24} color={colors.muted} strokeWidth={1.6} />
        <Text variant="label" tone="muted" style={styles.name}>
          Todas
        </Text>
      </Pressable>
    </View>
  );
}

// O espaço entre as casas da grade: metade de cada lado de cada uma.
const CELL_GAP = space.xs;

const styles = StyleSheet.create({
  grid: { flexDirection: 'row', flexWrap: 'wrap', marginHorizontal: -CELL_GAP / 2 },
  slot: { width: '25%', padding: CELL_GAP / 2 },
  cell: {
    minHeight: 64,
    alignItems: 'center',
    justifyContent: 'center',
    gap: space.xs,
    paddingVertical: space.sm,
    paddingHorizontal: space.xs,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  pressed: { backgroundColor: colors.pressed },
  selected: { borderColor: colors.accent, backgroundColor: colors.accentSoft },
  disabled: { opacity: 0.5 },
  name: { fontSize: 12, lineHeight: 14, textAlign: 'center' },
  sheetHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: space.md },
});
