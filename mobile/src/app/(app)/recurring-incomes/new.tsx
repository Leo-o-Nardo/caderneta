import { RecurringIncomeForm } from '@/components/RecurringIncomeForm';
import { Header, Screen } from '@/components/ui';

/** Cadastrar uma receita fixa pela Agenda (Contas › Receitas fixas › Adicionar). */
export default function NewRecurringIncomeScreen() {
  return (
    <Screen>
      <Header title="Nova receita fixa" leading="close" />
      <RecurringIncomeForm />
    </Screen>
  );
}
