import type { ChatCard } from '@/api/types';

import { AutopayAlertCard, BillDueAlertCard, BudgetAlertCard, ReminderAlertCard, WeeklySummaryAlertCard } from './AlertCards';
import { BarChartCard } from './BarChartCard';
import { BillPaymentCard } from './BillPaymentCard';
import { DeleteCard } from './DeleteCard';
import { DoneCard } from './DoneCard';
import { TransactionsCard } from './TransactionsCard';
import { TransactionUpdateCard } from './TransactionUpdateCard';

type Props = {
  card: ChatCard;
  // A mensagem do card: os botões dos avisos do app falam com a API por ela.
  messageId: number;
  // A hora da mensagem, para o topo das fichas de aviso ("09:12").
  time?: string;
};

/** Escolhe o componente de cada tipo de card que vem nas mensagens (do assistente ou dos avisos). */
export function ChatCardView({ card, messageId, time }: Props) {
  switch (card.type) {
    case 'bar_chart':
      return <BarChartCard card={card} />;
    case 'transactions_confirm':
      return <TransactionsCard card={card} />;
    case 'transaction_update':
      return <TransactionUpdateCard card={card} />;
    case 'bill_confirm':
      return <BillPaymentCard card={card} />;
    case 'delete_confirm':
    case 'list_delete':
      return <DeleteCard card={card} />;
    case 'list_update':
    case 'reminder_created':
    case 'bill_created':
    case 'income_created':
    case 'budget_set':
    case 'list_created':
      return <DoneCard card={card} />;
    case 'bill_due':
      return <BillDueAlertCard card={card} messageId={messageId} time={time} />;
    case 'reminder_due':
      return <ReminderAlertCard card={card} messageId={messageId} />;
    case 'autopay_launched':
      return <AutopayAlertCard card={card} messageId={messageId} time={time} />;
    case 'budget_alert':
      return <BudgetAlertCard card={card} time={time} />;
    case 'weekly_summary':
      return <WeeklySummaryAlertCard card={card} time={time} />;
  }
}
