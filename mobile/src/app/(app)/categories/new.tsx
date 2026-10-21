import { useLocalSearchParams } from 'expo-router';

import { CategoryForm } from '@/components/CategoryForm';
import { Header, Screen } from '@/components/ui';

export default function NewCategoryScreen() {
  // Abre no tipo da aba que estava aberta na lista (despesas ou receitas).
  const { kind } = useLocalSearchParams<{ kind?: string }>();

  return (
    <Screen>
      <Header title="Nova categoria" leading="close" />
      <CategoryForm kind={kind === 'income' ? 'income' : 'expense'} />
    </Screen>
  );
}
