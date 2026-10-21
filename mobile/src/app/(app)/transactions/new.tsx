import { TransactionForm } from '@/components/TransactionForm';
import { Header, Screen } from '@/components/ui';

export default function NewTransactionScreen() {
  return (
    <Screen>
      <Header title="Novo lançamento" leading="close" />
      <TransactionForm />
    </Screen>
  );
}
