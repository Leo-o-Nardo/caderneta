import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet } from 'react-native';

import { useCategories } from '@/api/categories';
import { CategoryForm } from '@/components/CategoryForm';
import { Header, Screen, Text } from '@/components/ui';
import { colors, space } from '@/theme/tokens';

export default function EditCategoryScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const categories = useCategories();
  const category = categories.data?.find((item) => item.id === Number(id));

  return (
    <Screen>
      <Header title="Categoria" leading="close" />

      {categories.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : category ? (
        <CategoryForm category={category} />
      ) : (
        <Text tone="muted" style={styles.status}>
          Esta categoria foi arquivada.
        </Text>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
});
