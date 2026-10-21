import { CardForm } from '@/components/CardForm';
import { Header, Screen } from '@/components/ui';

export default function NewCardScreen() {
  return (
    <Screen>
      <Header title="Novo cartão" leading="close" />
      <CardForm />
    </Screen>
  );
}
