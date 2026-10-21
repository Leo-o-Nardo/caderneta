import { format, parseISO } from 'date-fns';
import { router } from 'expo-router';
import { Bell, CalendarCheck, CalendarClock, ChartColumn, Check, Clock, Target, Undo2 } from 'lucide-react-native';
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import { alertAction, type AlertAction } from '@/api/alerts';
import type {
  AutopayLaunchedCard,
  BillDueCard,
  BudgetAlertCard as BudgetAlertCardData,
  ReminderDueCard,
  WeeklySummaryCard,
} from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { Amount, Button, Card, ProgressBar, Text } from '@/components/ui';
import { alertTimeLabel, billAlertAmount, weekRangeLabel, weeklyChange } from '@/lib/alerts';
import { BUDGET_COLORS } from '@/lib/budgets';
import { dayLabel, monthName, shortDate } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { colors, space } from '@/theme/tokens';

import { FichaBody, FichaFooter, FichaHeader, FichaItem, FichaLink, FichaResult } from './Ficha';

/*
 * As fichas dos avisos do app. Os botões passam pela API (/alerts/{mensagem}/{ação}), e a ficha passa
 * a mostrar o que aconteceu ("Feito", "Paga", "Desfeito"), mesmo depois de reabrir o Chat. A hora do
 * aviso fica no topo, ao lado do rótulo.
 */

type AlertProps<T> = {
  card: T;
  messageId: number;
  // A hora em que o aviso chegou ("09:12").
  time?: string;
};

const BILL_TITLES: Record<BillDueCard['when'], string> = {
  tomorrow: 'Vence amanhã',
  today: 'Vence hoje',
  autopay: 'Débito automático · informe o valor',
};

/** "Luz vence amanhã": "Paguei" abre a tela de pagar; "Lembrar amanhã" cria um lembrete para as 9h. */
export function BillDueAlertCard({ card, messageId, time }: AlertProps<BillDueCard>) {
  const { busy, error, run, changed } = useAlertAction(messageId, card.state);

  return (
    <Card>
      <FichaHeader icon={CalendarClock} title={BILL_TITLES[card.when]} tone={card.state ? 'muted' : 'warn'} aside={time} />

      <FichaItem
        icon={<CategoryIcon icon={card.bill.category.icon} color={card.bill.category.color} />}
        title={card.bill.name}
        detail={`${card.bill.category.name} · vence ${shortDate(card.due_on)}`}
        value={<Text variant="amount">{billAlertAmount(card)}</Text>}
      />

      <FichaFooter>
        {card.state === 'paid' ? (
          <FichaResult done animate={changed} text="Paga" />
        ) : card.state === 'reminded' && card.reminder_at ? (
          <FichaResult icon={Clock} text={`Vou lembrar ${alertTimeLabel(card.reminder_at)}`} />
        ) : (
          <View style={styles.buttons}>
            {card.when !== 'autopay' ? (
              <Button
                title="Lembrar amanhã"
                variant="secondary"
                size="sm"
                loading={busy === 'remind_tomorrow'}
                disabled={busy !== null}
                onPress={() => run('remind_tomorrow')}
                style={styles.button}
              />
            ) : null}
            <Button
              title="Paguei"
              size="sm"
              icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
              disabled={busy !== null}
              onPress={() =>
                router.push({
                  pathname: '/bills/pay',
                  // Com o aviso junto, a tela de pagar avisa a ficha no fim ("Paga").
                  params: { bill_id: String(card.bill.id), month: card.month, alert_id: String(messageId) },
                })
              }
              style={styles.button}
            />
          </View>
        )}
        <AlertError error={error} />
      </FichaFooter>
    </Card>
  );
}

/** O lembrete na hora marcada: "Feito", "Adiar 1 h" ou "Amanhã" (às 9h). */
export function ReminderAlertCard({ card, messageId }: AlertProps<ReminderDueCard>) {
  const { busy, error, run, changed } = useAlertAction(messageId, card.state);

  return (
    <Card>
      <FichaHeader
        icon={Bell}
        title="Lembrete"
        tone={card.state ? 'muted' : 'warn'}
        aside={format(parseISO(card.reminder.remind_at), 'HH:mm')}
      />

      <FichaBody>
        <Text variant="strong">{card.reminder.text}</Text>
      </FichaBody>

      <FichaFooter>
        {card.state === 'done' ? (
          <FichaResult done animate={changed} text="Feito" />
        ) : card.state === 'snoozed' && card.snoozed_until ? (
          <FichaResult icon={Clock} text={`Adiado: aviso de novo ${alertTimeLabel(card.snoozed_until)}`} />
        ) : (
          <View style={styles.buttons}>
            <Button
              title="Adiar 1 h"
              variant="secondary"
              size="sm"
              loading={busy === 'snooze_hour'}
              disabled={busy !== null}
              onPress={() => run('snooze_hour')}
              style={styles.button}
            />
            <Button
              title="Amanhã"
              variant="secondary"
              size="sm"
              loading={busy === 'tomorrow'}
              disabled={busy !== null}
              onPress={() => run('tomorrow')}
              style={styles.button}
            />
            <Button
              title="Feito"
              size="sm"
              icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
              loading={busy === 'done'}
              disabled={busy !== null}
              onPress={() => run('done')}
              style={styles.button}
            />
          </View>
        )}
        <AlertError error={error} />
      </FichaFooter>
    </Card>
  );
}

/** "Internet: R$ 99,90 lançada no débito automático", com "Desfazer" (o mês volta a ficar em aberto). */
export function AutopayAlertCard({ card, messageId, time }: AlertProps<AutopayLaunchedCard>) {
  const { busy, error, run } = useAlertAction(messageId, card.state);
  const undone = card.state === 'undone';

  return (
    <Card>
      <FichaHeader icon={CalendarCheck} title="Débito automático" aside={time ?? monthName(card.month)} />

      <FichaItem
        icon={<CategoryIcon icon={card.bill.category.icon} color={card.bill.category.color} />}
        title={card.bill.name}
        detail={`Lançada no débito · ${monthName(card.month)}`}
        value={<Amount cents={card.amount_cents} tone={undone ? 'faint' : undefined} />}
        struck={undone}
      />

      <FichaFooter>
        {undone ? (
          <FichaResult icon={Undo2} text="Desfeito: o mês voltou a ficar em aberto" />
        ) : (
          <Button
            title="Desfazer"
            variant="ghost"
            icon={<Undo2 size={18} color={colors.accent} strokeWidth={1.6} />}
            loading={busy === 'undo'}
            disabled={busy !== null}
            onPress={() => run('undo')}
          />
        )}
        <AlertError error={error} />
      </FichaFooter>
    </Card>
  );
}

/**
 * "Mercado chegou a 85% do limite do mês": o alvo no topo, o ícone da categoria, a porcentagem na
 * cor do estado, a barra e "Ver lançamentos de Mercado" (os gastos da categoria no mês).
 */
export function BudgetAlertCard({ card, time }: Omit<AlertProps<BudgetAlertCardData>, 'messageId'>) {
  const over = card.threshold === 100;
  const tone = over ? 'danger' : 'warn';
  const left =
    card.remaining_cents < 0
      ? `passou ${formatCents(-card.remaining_cents)} do limite`
      : `restam ${formatCents(card.remaining_cents)} em ${monthName(card.month)}`;

  return (
    <Card>
      <FichaHeader
        icon={Target}
        title={over ? 'Orçamento estourado' : 'Orçamento perto do limite'}
        tone={tone}
        aside={time ?? monthName(card.month)}
      />

      <FichaBody>
        <View style={styles.line}>
          <View style={styles.name}>
            <CategoryIcon icon={card.category.icon} color={card.category.color} size={20} />
            <Text variant="strong" numberOfLines={1} style={styles.shrink}>
              {card.category.name}
            </Text>
          </View>
          <Text variant="figure" tone={tone}>
            {card.percent}%
          </Text>
        </View>
        <ProgressBar percent={card.percent} color={BUDGET_COLORS[card.status]} />
        <Text variant="caption" tone="muted">
          {formatCents(card.spent_cents)} de {formatCents(card.limit_cents)} · {left}
        </Text>
      </FichaBody>

      <FichaLink
        label={`Ver lançamentos de ${card.category.name}`}
        onPress={() =>
          router.push({
            pathname: '/transactions',
            params: { month: card.month, category_id: String(card.category.id), category_name: card.category.name },
          })
        }
      />
    </Card>
  );
}

/**
 * O resumo de domingo: as despesas da semana comparadas com a anterior, as categorias que mais
 * pesaram (barras finas em Tinta, proporcionais à maior) e as contas dos próximos 7 dias.
 */
export function WeeklySummaryAlertCard({ card, time }: Omit<AlertProps<WeeklySummaryCard>, 'messageId'>) {
  const change = weeklyChange(card.spent_cents, card.previous_cents);
  const biggest = card.categories[0]?.total_cents ?? 0;

  return (
    <Card>
      <FichaHeader icon={ChartColumn} title={`Resumo da semana · ${weekRangeLabel(card.from, card.to)}`} aside={time} />

      <FichaBody>
        <Text variant="caption" tone="muted">
          Despesas da semana
        </Text>
        <Amount cents={card.spent_cents} variant="figure" />
        <Text variant="caption" tone={change?.lower ? 'accent' : 'muted'}>
          {change ? change.text : card.spent_cents === 0 ? 'Nenhum gasto lançado na semana' : 'Gasto da semana'}
        </Text>
      </FichaBody>

      {card.categories.length > 0 ? (
        <FichaBody divided>
          {card.categories.map((category) => (
            <View key={category.id} style={styles.category}>
              <View style={styles.line}>
                <View style={styles.name}>
                  <CategoryIcon icon={category.icon} color={category.color} size={20} />
                  <Text variant="strong" numberOfLines={1} style={styles.shrink}>
                    {category.name}
                  </Text>
                </View>
                <Amount cents={category.total_cents} />
              </View>
              <ProgressBar percent={(category.total_cents / biggest) * 100} color={colors.ink} trackless />
            </View>
          ))}
        </FichaBody>
      ) : null}

      {card.bills.length > 0 ? (
        <>
          <FichaBody divided>
            <Text variant="overline" tone="muted">
              Contas nos próximos 7 dias
            </Text>
          </FichaBody>
          {card.bills.map((bill) => (
            <FichaItem
              key={`${bill.bill.id}-${bill.month}`}
              icon={<CategoryIcon icon={bill.bill.category.icon} color={bill.bill.category.color} />}
              title={bill.bill.name}
              detail={dayLabel(bill.due_on)}
              value={<Text variant="amount">{billAlertAmount(bill)}</Text>}
            />
          ))}
        </>
      ) : null}
    </Card>
  );
}

function AlertError({ error }: { error: string | null }) {
  return error ? (
    <Text variant="caption" tone="danger">
      {error}
    </Text>
  ) : null;
}

/**
 * O botão em andamento e o erro de uma ficha de aviso. "changed" diz se o estado mudou com a ficha
 * na tela: só então o ✓ se desenha (ao rolar o Chat, ele já aparece pronto).
 */
function useAlertAction(messageId: number, state: string | null) {
  const [busy, setBusy] = useState<AlertAction | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [initialState] = useState(state);

  async function run(action: AlertAction) {
    setBusy(action);
    setError(null);

    try {
      await alertAction(messageId, action);
    } catch (failure) {
      setError(failure instanceof Error ? failure.message : 'Tente de novo.');
    } finally {
      setBusy(null);
    }
  }

  return { busy, error, run, changed: initialState !== state };
}

const styles = StyleSheet.create({
  buttons: { flexDirection: 'row', gap: space.sm },
  button: { flex: 1 },
  line: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: space.md },
  name: { flexDirection: 'row', alignItems: 'center', gap: space.sm, flexShrink: 1 },
  shrink: { flexShrink: 1 },
  category: { gap: space.xs },
});
