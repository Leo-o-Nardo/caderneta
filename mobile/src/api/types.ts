/**
 * Os formatos das respostas da API (ver os Resources do backend). Valores sempre em centavos;
 * datas como "2026-10-07" e meses como "2026-10".
 */

export type HouseholdRole = 'owner' | 'member' | 'viewer';

export type User = {
  id: number;
  name: string;
  email: string;
  timezone: string;
  // Configurações › Avisos: cada tipo ligado ou não.
  alert_settings: AlertSettings;
  current_household: {
    id: number;
    name: string;
    role: HouseholdRole;
  } | null;
};

/** Os avisos que dá para desligar. O lembrete não está aqui: ele sempre avisa. */
export type AlertSettings = {
  bill_due: boolean;
  autopay: boolean;
  budget: boolean;
  weekly: boolean;
};

export type AuthResponse = {
  token: string;
  user: User;
};

export type Member = {
  id: number;
  name: string;
  email: string;
  role: HouseholdRole;
  joined_at: string;
  is_you: boolean;
};

export type Household = {
  id: number;
  name: string;
  role: HouseholdRole;
  is_current: boolean;
  members_count: number;
  // Só na casa atual (GET /household).
  members?: Member[];
};

export type Invitation = {
  id: number;
  code: string;
  url: string;
  role: HouseholdRole;
  invited_by: string;
  expires_at: string;
  // Só na visualização de quem recebeu o convite.
  household?: { name: string; members_count: number };
};

export type CategoryKind = 'expense' | 'income';

export type PaymentType = 'debit' | 'credit_card' | 'pix' | 'cash';

export type Visibility = 'household' | 'private';

/** A categoria resumida que vem dentro de lançamentos, orçamentos e contas. */
export type CategoryRef = {
  id: number;
  name: string;
  icon: string;
  color: string;
};

export type Category = CategoryRef & {
  kind: CategoryKind;
};

export type InvoicePeriod = {
  month: string;
  closes_on: string;
  due_on: string;
};

export type Card = {
  id: number;
  name: string;
  closing_day: number;
  due_day: number;
  limit_cents: number | null;
  // Tudo o que ainda não venceu: fatura aberta, parcelas futuras e a fechada não vencida.
  limit_used_cents: number;
  color: string;
  is_default: boolean;
  is_mine: boolean;
  owner: { id: number; name: string };
  // A fatura em que cairia uma compra feita hoje, com o total até agora.
  current_invoice: InvoicePeriod & { total_cents: number };
};

export type Invoice = InvoicePeriod & {
  card: { id: number; name: string; owner: string };
  is_closed: boolean;
  total_cents: number;
  installments_cents: number;
  items: Transaction[];
};

export type ListKind = 'shopping' | 'wishlist' | 'todo' | 'custom';

export type HouseholdList = {
  id: number;
  name: string;
  kind: ListKind;
  icon: string;
  is_default: boolean;
  visibility: Visibility;
  position: number;
  items_count: number;
  open_items_count: number;
  // Soma dos preços dos itens em aberto (a lista de desejos usa).
  open_total_cents: number;
};

export type ListItem = {
  id: number;
  list_id: number;
  text: string;
  quantity: string | null;
  note: string | null;
  price_cents: number | null;
  url: string | null;
  due_on: string | null;
  position: number;
  is_checked: boolean;
  checked_at: string | null;
  checked_by: { id: number; name: string } | null;
};

export type Recurrence = 'none' | 'daily' | 'weekly' | 'monthly' | 'yearly';

export type Reminder = {
  id: number;
  text: string;
  // No fuso do usuário, com o deslocamento: "2026-10-20T09:00:00-03:00".
  remind_at: string;
  recurrence: Recurrence;
  // "A cada N" dias, semanas, meses ou anos.
  every: number;
  visibility: Visibility;
  is_done: boolean;
  is_overdue: boolean;
  created_by: { id: number; name: string } | null;
};

export type Transaction = {
  id: number;
  kind: CategoryKind;
  amount_cents: number;
  description: string;
  occurred_on: string;
  category: CategoryRef;
  payment_type: PaymentType | null;
  card: { id: number; name: string } | null;
  invoice_month: string | null;
  installment: { plan_id: number; number: number; count: number } | null;
  // A receita fixa de onde veio o lançamento, enquanto ela continua repetindo.
  recurring_income: { id: number; day_of_month: number } | null;
  visibility: Visibility;
  // "recurring": lançado sozinho no dia pela receita fixa.
  source: 'app' | 'assistant' | 'recurring';
  created_by: { id: number; name: string } | null;
  can_edit: boolean;
};

/** Uma receita que se repete todo mês (salário). A API lança cada mês sozinha, no dia. */
export type RecurringIncome = {
  id: number;
  description: string;
  amount_cents: number;
  day_of_month: number;
  category: CategoryRef;
  visibility: Visibility;
  created_by: { id: number; name: string } | null;
  // O dia do próximo lançamento automático ("2026-11-05").
  next_on: string;
  can_edit: boolean;
};

export type InstallmentPlan = {
  id: number;
  description: string;
  total_cents: number;
  installments: number;
  purchased_on: string;
  category: { id: number; name: string };
  card: { id: number; name: string } | null;
  visibility: Visibility;
  created_by: { id: number; name: string } | null;
  can_edit: boolean;
  transactions?: Transaction[];
};

/** Uma página da lista paginada por cursor (o Laravel devolve o próximo cursor em meta). */
export type CursorPage<T> = {
  data: T[];
  meta: { next_cursor: string | null; per_page: number };
};

export type BudgetStatus = 'ok' | 'warning' | 'over';

export type BudgetUsage = {
  category: CategoryRef;
  limit_cents: number;
  spent_cents: number;
  remaining_cents: number;
  percent: number;
  status: BudgetStatus;
};

/** Uma categoria de despesa com ou sem orçamento, para definir o limite. */
export type BudgetDetail = {
  category: CategoryRef;
  limit_cents: number | null;
  // O gasto no mês atual.
  spent_cents: number;
  // A média dos últimos meses fechados (até 3); null enquanto não houver nenhum.
  average_cents: number | null;
  average_months: number;
};

export type BillStatus = 'paid' | 'skipped' | 'overdue' | 'due_today' | 'upcoming';

/** Um mês de uma conta fixa. Sem "bill" quando já vem dentro da conta (open, history). */
export type BillMonth = {
  month: string;
  due_on: string;
  // O valor pago ou, em aberto, o fixo ou a estimativa (is_estimate). Nulo quando não há nenhum.
  amount_cents: number | null;
  is_estimate: boolean;
  status: BillStatus;
  // Negativo quando já venceu.
  days_until_due: number;
  transaction_id: number | null;
};

export type BillOccurrence = BillMonth & {
  bill: {
    id: number;
    name: string;
    amount_varies: boolean;
    autopay: boolean;
    category: CategoryRef;
  };
};

export type Bill = {
  id: number;
  name: string;
  expected_cents: number | null;
  amount_varies: boolean;
  // O valor usado nos totais: o fixo, ou a média dos últimos pagamentos se o valor varia.
  estimate_cents: number | null;
  due_day: number;
  autopay: boolean;
  starts_on: string;
  category: CategoryRef;
  // O mês que um "Paguei" agora quitaria; null quando está tudo em dia.
  open: BillMonth | null;
};

export type BillDetail = Bill & {
  // Os últimos 6 meses, do mais recente ao mais antigo.
  history: BillMonth[];
};

export type Summary = {
  month: string;
  income_cents: number;
  expense_cents: number;
  balance_cents: number;
  by_category: (CategoryRef & { total_cents: number })[];
  by_member: { id: number; name: string; total_cents: number }[];
  invoices: (InvoicePeriod & { card: { id: number; name: string; owner: string }; total_cents: number })[];
  budgets: BudgetUsage[];
  upcoming_bills: BillOccurrence[];
};

/*
 * O assistente. As mensagens trazem cards tipados: o app desenha cada tipo com um componente, a
 * partir dos números que vieram das ferramentas (nunca do texto da IA).
 */

export type ActionStatus = 'pending' | 'executed' | 'cancelled' | 'expired' | 'undone';

/** O pedaço "action" de todo card de ação: os botões dependem do nível e do status. */
export type CardAction = {
  id: string;
  tool: string;
  // immediate: já gravou, tem "Desfazer". confirm: espera confirmar. explicit: apagar, com o impacto.
  tier: 'immediate' | 'confirm' | 'explicit';
  status: ActionStatus;
  expires_at: string | null;
  undo_until: string | null;
};

export type BarChartCard = {
  type: 'bar_chart';
  title: string;
  subtitle: string;
  total: string;
  bars: { label: string; value_cents: number; value: string; color: string | null }[];
};

export type TransactionsConfirmCard = {
  type: 'transactions_confirm';
  action: CardAction;
  items: {
    kind: CategoryKind;
    amount_cents: number;
    description: string;
    occurred_on: string;
    category: CategoryRef | null;
    payment_type: PaymentType | null;
    card: { id: number; name: string } | null;
    installments: number | null;
  }[];
  total_cents: number;
};

export type BillConfirmCard = {
  type: 'bill_confirm';
  action: CardAction;
  bill: { id: number; name: string; amount_varies: boolean; category: CategoryRef } | null;
  month: string;
  due_on: string | null;
  // Em branco quando a conta varia e ninguém disse o valor.
  amount_cents: number | null;
  estimate_cents: number | null;
  paid_on: string | null;
  payment_type: PaymentType | null;
  card: { id: number; name: string } | null;
};

export type DeleteConfirmCard = {
  type: 'delete_confirm';
  action: CardAction;
  transaction: {
    id: number;
    kind: CategoryKind;
    description: string;
    amount_cents: number;
    occurred_on: string;
    category: CategoryRef;
  };
  impact: string;
};

export type ListUpdateCard = {
  type: 'list_update';
  action: CardAction;
  list: { id: number; name: string; kind: ListKind } | null;
  added: string[];
  already: string[];
  checked: string[];
  not_found: string[];
};

export type ReminderCreatedCard = {
  type: 'reminder_created';
  action: CardAction;
  reminder: {
    id: number | null;
    text: string;
    remind_at: string;
    recurrence: Recurrence;
    every: number;
    visibility: Visibility;
  };
};

/** "O café de ontem foi 15": o lançamento e uma linha por campo que muda, já escrita pela API. */
export type TransactionUpdateCard = {
  type: 'transaction_update';
  action: CardAction;
  transaction: {
    id: number;
    kind: CategoryKind;
    description: string;
    amount_cents: number;
    occurred_on: string;
    category: CategoryRef;
  };
  changes: { field: 'amount' | 'description' | 'category' | 'date' | 'payment'; label: string; before: string; after: string }[];
};

/** "Apaga a lista de churrasco": a lista e o que some junto. */
export type ListDeleteCard = {
  type: 'list_delete';
  action: CardAction;
  list: { id: number; name: string; kind: ListKind };
  impact: string;
};

export type BillCreatedCard = {
  type: 'bill_created';
  action: CardAction;
  bill: {
    id: number | null;
    name: string;
    expected_cents: number | null;
    amount_varies: boolean;
    due_day: number;
    autopay: boolean;
    category: CategoryRef | null;
    first_due_on: string | null;
  };
};

export type IncomeCreatedCard = {
  type: 'income_created';
  action: CardAction;
  income: {
    id: number | null;
    description: string;
    amount_cents: number;
    day_of_month: number;
    first_on: string | null;
    category: CategoryRef | null;
    visibility: Visibility;
  };
  // O dia era hoje: a receita de hoje já entrou.
  launched_today: boolean;
};

export type BudgetSetCard = {
  type: 'budget_set';
  action: CardAction;
  category: CategoryRef | null;
  limit_cents: number;
  // O limite que havia antes (null: a categoria não tinha orçamento).
  previous_cents: number | null;
  spent_cents: number;
};

export type ListCreatedCard = {
  type: 'list_created';
  action: CardAction;
  list: { id: number | null; name: string; kind: ListKind };
  items: string[];
};

/** Os cards das ações gravadas na hora, com "Desfazer". */
export type DoneActionCard =
  | ListUpdateCard
  | ReminderCreatedCard
  | BillCreatedCard
  | IncomeCreatedCard
  | BudgetSetCard
  | ListCreatedCard;

export type ActionCard =
  | TransactionsConfirmCard
  | TransactionUpdateCard
  | BillConfirmCard
  | DeleteConfirmCard
  | ListDeleteCard
  | DoneActionCard;

/** A conta de um card de aviso. */
type AlertBill = { id: number; name: string; amount_varies: boolean; category: CategoryRef };

/** "Luz vence amanhã": com "Paguei" e "Lembrar amanhã". */
export type BillDueCard = {
  type: 'bill_due';
  bill: AlertBill;
  month: string;
  due_on: string;
  // O valor fixo ou a estimativa (is_estimate); null quando a conta não tem valor cadastrado.
  amount_cents: number | null;
  is_estimate: boolean;
  // "autopay": débito automático de valor que varia, esperando o valor para lançar.
  when: 'tomorrow' | 'today' | 'autopay';
  state: 'paid' | 'reminded' | null;
  reminder_at: string | null;
};

/** O lembrete na hora marcada: com "Feito", "Adiar 1 h" e "Amanhã". */
export type ReminderDueCard = {
  type: 'reminder_due';
  reminder: { id: number; text: string; remind_at: string; recurrence: Recurrence; every: number };
  state: 'done' | 'snoozed' | null;
  snoozed_until: string | null;
};

/** "Internet: R$ 99,90 lançada no débito automático": com "Desfazer". */
export type AutopayLaunchedCard = {
  type: 'autopay_launched';
  bill: AlertBill;
  month: string;
  amount_cents: number;
  transaction_id: number | null;
  state: 'undone' | null;
};

/** "Mercado chegou a 85% do limite do mês": com a barra e "Ver lançamentos". */
export type BudgetAlertCard = BudgetUsage & {
  type: 'budget_alert';
  month: string;
  // O degrau que este aviso marca.
  threshold: 80 | 100;
};

/** O resumo de domingo: a semana (segunda a domingo) com os números de quem recebeu. */
export type WeeklySummaryCard = {
  type: 'weekly_summary';
  from: string;
  to: string;
  spent_cents: number;
  previous_cents: number;
  // As que mais pesaram, da maior para a menor (no máximo 4).
  categories: (CategoryRef & { total_cents: number })[];
  // As contas em aberto que vencem nos próximos 7 dias.
  bills: BillOccurrence[];
};

/** Os cards dos avisos do app (mensagens com role "event"). */
export type AlertCard = BillDueCard | ReminderDueCard | AutopayLaunchedCard | BudgetAlertCard | WeeklySummaryCard;

export type ChatCard = BarChartCard | ActionCard | AlertCard;

export type ChatMessage = {
  id: number;
  // event: avisos do próprio app (conta vencendo, orçamento estourado), na fase 7.
  role: 'user' | 'assistant' | 'event';
  // Vazio enquanto a resposta está sendo preparada (ou a voz, transcrita).
  content: string | null;
  // "transcribing": a mensagem de voz ainda está virando texto.
  status: 'pending' | 'transcribing' | 'completed' | 'failed';
  cards: ChatCard[];
  source: 'chat' | 'home' | 'cli' | null;
  // "audio": veio de voz (a bolha mostra o microfone e a duração).
  kind: 'text' | 'audio';
  duration_ms: number | null;
  created_at: string;
};

/** Os passos do checklist do começo (OnboardingService na API), na ordem da tela. */
export type OnboardingStepKey =
  | 'household_name'
  | 'first_transaction'
  | 'cards'
  | 'recurring_income'
  | 'recurring_bills'
  | 'categories'
  | 'invite';

export type OnboardingStep = {
  key: OnboardingStepKey;
  // done sai dos cadastros de verdade; skipped é o que a pessoa escolheu ignorar.
  status: 'done' | 'pending' | 'skipped';
  // O que foi feito ("Nubank e Inter"); null enquanto não está feito.
  detail: string | null;
  skippable: boolean;
};

/** Os primeiros passos da pessoa na casa atual: boas-vindas, checklist e o ponto do tour. */
export type Onboarding = {
  welcome_seen: boolean;
  // Entrou por convite (não é dona da casa): as boas-vindas ficam mais curtas.
  joined: boolean;
  // O bloco do Início foi escondido.
  hidden: boolean;
  tour: { step: number | null; completed: boolean };
  steps: OnboardingStep[];
  done: number;
  skipped: number;
  total: number;
};

/** Uma escolha por vez, como a API aceita (PATCH /onboarding). */
export type OnboardingChange = {
  welcome_seen?: true;
  hidden?: boolean;
  skip?: OnboardingStepKey;
  unskip?: OnboardingStepKey;
  categories_reviewed?: true;
  tour_step?: number | null;
  tour_completed?: true;
};
