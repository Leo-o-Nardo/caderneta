import { BillForm } from '@/components/BillForm';
import { Header, Screen } from '@/components/ui';

export default function NewBillScreen() {
  return (
    <Screen>
      <Header title="Nova conta fixa" leading="close" />
      <BillForm />
    </Screen>
  );
}
