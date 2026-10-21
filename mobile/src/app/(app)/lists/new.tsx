import { ListForm } from '@/components/ListForm';
import { Header, Screen } from '@/components/ui';

export default function NewListScreen() {
  return (
    <Screen>
      <Header title="Nova lista" leading="close" />
      <ListForm />
    </Screen>
  );
}
