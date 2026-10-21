import { ReminderForm } from '@/components/ReminderForm';
import { Header, Screen } from '@/components/ui';

export default function NewReminderScreen() {
  return (
    <Screen>
      <Header title="Novo lembrete" leading="close" />
      <ReminderForm />
    </Screen>
  );
}
