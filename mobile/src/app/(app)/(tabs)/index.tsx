import * as Haptics from 'expo-haptics';
import { router } from 'expo-router';
import { ArrowUp, ChevronRight, CircleAlert, Mic, Settings } from 'lucide-react-native';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, View } from 'react-native';
import Animated, {
  Easing,
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withRepeat,
  withTiming,
} from 'react-native-reanimated';

import { sendVoice, type VoiceRecording } from '@/api/assistant';
import { useCanWrite, useMe } from '@/api/auth';
import { todaysFilters } from '@/api/home';
import { useOnboarding } from '@/api/onboarding';
import { useSummary } from '@/api/summary';
import { useTransactions } from '@/api/transactions';
import type { BillOccurrence, Summary } from '@/api/types';
import { Ledger } from '@/components/Ledger';
import { OnboardingBlock } from '@/components/OnboardingBlock';
import { PushPrompt } from '@/components/PushPrompt';
import { TourTarget } from '@/components/tour/TourTarget';
import { TransactionRow } from '@/components/TransactionRow';
import { Amount, Button, IconButton, Screen, SectionHeader, Text } from '@/components/ui';
import { dueNoticeCaption, dueNoticeTitle } from '@/lib/bills';
import { overBudgetLabel, remainingInBudgets } from '@/lib/budgets';
import { formatDuration } from '@/lib/chat';
import { firstName, fullDayLabel, greeting, monthName, todayISO, toMonth } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { MAX_VOICE_MS, useVoiceRecorder } from '@/lib/useVoiceRecorder';
import { useInvite } from '@/stores/invite';
import { colors, radius, space } from '@/theme/tokens';

/**
 * O Início: abrir e lançar. O microfone grava uma mensagem para o assistente (um toque começa,
 * outro envia) e o app vai para o Chat ver a transcrição e a resposta; "ou lançar manualmente"
 * abre o formulário. Em cima, o mês em duas colunas de caderneta; embaixo, o que foi lançado hoje.
 */
export default function HomeScreen() {
  const { data: user } = useMe();
  const canWrite = useCanWrite();
  const now = new Date();
  const month = toMonth(now);
  const today = todayISO();
  const summary = useSummary(month);
  const todays = useTransactions(todaysFilters(today));
  const items = todays.data?.pages[0]?.data ?? [];
  const spentToday = items.filter((item) => item.kind === 'expense').reduce((total, item) => total + item.amount_cents, 0);
  const pendingInvite = useInvite((state) => state.pendingCode);
  const [sending, setSending] = useState(false);

  // A voz cai na mesma conversa do Chat. Só troca de aba depois que a API aceitou o áudio: se
  // falhar, a pessoa continua aqui e vê o aviso.
  async function send(recording: VoiceRecording | null) {
    if (recording === null) {
      Alert.alert('Gravação curta demais', 'Toque no microfone, fale e toque de novo para enviar.');

      return;
    }

    setSending(true);

    try {
      await sendVoice(recording, 'home');
      router.navigate('/chat');
    } catch (error) {
      Alert.alert('Não deu para enviar o áudio', error instanceof Error ? error.message : 'Tente de novo.');
    } finally {
      setSending(false);
    }
  }

  // Aos 60 s a gravação para e vai sozinha.
  const voice = useVoiceRecorder((recording) => send(recording));

  async function onMicPress() {
    if (sending) {
      return;
    }

    if (voice.isRecording) {
      await send(await voice.finish());
    } else {
      // Uma vibração leve confirma que começou a gravar.
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      await voice.start();
    }
  }

  // Quem abriu um convite antes de entrar na conta: depois do login, o convite abre de novo.
  useEffect(() => {
    if (pendingInvite) {
      useInvite.getState().setPendingCode(null);
      router.push({ pathname: '/convite/[code]', params: { code: pendingInvite } });
    }
  }, [pendingInvite]);

  // A primeira entrada em cada casa abre as boas-vindas uma vez. Os dados chegam junto com os do
  // Início (prefetchHome), então elas já estão na tela quando a capa da abertura se abre.
  const onboarding = useOnboarding();
  const welcomedHousehold = useRef<number | null>(null);
  const householdId = user?.current_household?.id;

  useEffect(() => {
    if (onboarding.data && !onboarding.data.welcome_seen && householdId && welcomedHousehold.current !== householdId) {
      welcomedHousehold.current = householdId;
      router.push('/onboarding/welcome');
    }
  }, [onboarding.data, householdId]);

  return (
    <Screen edges={['top']}>
      <View style={styles.top}>
        <Text variant="overline" tone="muted">
          {fullDayLabel(now)}
        </Text>
        <TourTarget id="home.settings">
          <IconButton icon={Settings} label="Configurações" onPress={() => router.push('/settings')} />
        </TourTarget>
      </View>
      <Text variant="title" style={styles.greeting}>
        {greeting()}
        {user ? `, ${firstName(user.name)}` : ''}
      </Text>

      {summary.data ? (
        <TourTarget id="home.month">
          <MonthLedger month={month} day={now.getDate()} summary={summary.data} />
        </TourTarget>
      ) : null}
      {summary.data ? <DueNotice bills={summary.data.upcoming_bills} /> : null}
      <OnboardingBlock />

      <View style={styles.center}>
        <TourTarget id="home.mic" style={styles.micGroup}>
          <MicButton recording={voice.isRecording} sending={sending} onPress={onMicPress} />

          {voice.isRecording ? (
            <>
              <Text variant="strong" style={styles.tabular}>
                {formatDuration(voice.durationMs)} · toque para enviar
              </Text>
              <Text variant="caption" tone="muted">
                até {formatDuration(MAX_VOICE_MS)}
              </Text>
              <Button title="Cancelar" variant="ghost" onPress={() => voice.cancel()} style={styles.centered} />
            </>
          ) : (
            <>
              <Text variant="strong">{sending ? 'Enviando…' : 'Toque e fale'}</Text>
              {/* O microfone fica para todos (o "Só leitura" pergunta); lançar à mão, não. */}
              {canWrite ? (
                <Button
                  title="ou lançar manualmente"
                  variant="ghost"
                  onPress={() => router.push('/transactions/new')}
                  style={styles.centered}
                />
              ) : null}
            </>
          )}
        </TourTarget>
      </View>

      <TourTarget id="home.today">
        <SectionHeader
          title="Hoje"
          detail={spentToday > 0 ? formatCents(spentToday) : undefined}
          action={{ label: 'Ver todos', onPress: () => router.push('/transactions') }}
          style={styles.todayHeader}
        />
        {items.length > 0 ? (
          items.slice(0, 3).map((transaction) => <TransactionRow key={transaction.id} transaction={transaction} />)
        ) : (
          <Text variant="caption" tone="muted" style={styles.emptyToday}>
            {todays.isPending ? 'Carregando…' : 'Nada lançado hoje ainda.'}
          </Text>
        )}
      </TourTarget>

      {/* Depois das boas-vindas, uma vez por aparelho: os avisos também no celular. */}
      <PushPrompt ready={onboarding.data?.welcome_seen === true} />
    </Screen>
  );
}

/**
 * O mês em duas colunas: as despesas até hoje e o que resta nos orçamentos (a legenda diz quais
 * estouraram e por isso não entram na conta). Sem nenhum orçamento, a segunda coluna mostra as
 * receitas do mês.
 */
function MonthLedger({ month, day, summary }: { month: string; day: number; summary: Summary }) {
  const name = monthName(month);
  const over = overBudgetLabel(summary.budgets);
  const count = summary.budgets.length;

  return (
    <View style={styles.ledger}>
      <Ledger
        cells={[
          {
            label: `Despesas de ${name}`,
            value: <Amount cents={summary.expense_cents} variant="figure" />,
            note: (
              <Text variant="caption" tone="muted">
                até hoje, dia {day}
              </Text>
            ),
          },
          count > 0
            ? {
                label: 'Resta nos orçamentos',
                value: <Amount cents={remainingInBudgets(summary.budgets)} variant="figure" />,
                note: (
                  <Text variant="caption" tone={over ? 'danger' : 'muted'} numberOfLines={1}>
                    {over ?? `em ${count} ${count === 1 ? 'categoria' : 'categorias'}`}
                  </Text>
                ),
              }
            : {
                label: `Receitas de ${name}`,
                value: <Amount cents={summary.income_cents} kind="income" variant="figure" />,
              },
        ]}
      />
    </View>
  );
}

/**
 * O aviso das contas atrasadas ou que vencem hoje: um traço Tijolo (atrasada) ou de Atenção (vence
 * hoje), o nome delas e o total. Tocar leva à Agenda. Sem nada vencendo, não aparece.
 */
function DueNotice({ bills }: { bills: BillOccurrence[] }) {
  const due = bills.filter((bill) => bill.status === 'overdue' || bill.status === 'due_today');

  if (due.length === 0) {
    return null;
  }

  const overdue = due.some((bill) => bill.status === 'overdue');

  return (
    <Pressable
      onPress={() => router.push('/agenda')}
      style={({ pressed }) => [styles.notice, overdue ? styles.noticeOverdue : styles.noticeToday, pressed && styles.pressed]}
      accessibilityRole="button"
    >
      <CircleAlert size={20} color={overdue ? colors.danger : colors.warn} strokeWidth={1.6} />
      <View style={styles.flex}>
        <Text variant="strong">{dueNoticeTitle(due)}</Text>
        <Text variant="caption" tone="muted">
          {dueNoticeCaption(due)}
        </Text>
      </View>
      <ChevronRight size={18} color={colors.faint} strokeWidth={1.6} />
    </Pressable>
  );
}

type MicProps = {
  recording: boolean;
  sending: boolean;
  onPress: () => void;
};

/** O círculo de Tinta do microfone. Gravando, fica Tijolo, com um anel fino se espalhando em volta. */
function MicButton({ recording, sending, onPress }: MicProps) {
  return (
    <View style={styles.micArea}>
      {recording ? <PulseRing /> : null}
      <Pressable
        onPress={onPress}
        disabled={sending}
        style={({ pressed }) => [
          styles.mic,
          recording && styles.micRecording,
          pressed && (recording ? styles.micRecordingPressed : styles.micPressed),
        ]}
        accessibilityRole="button"
        accessibilityLabel={recording ? 'Enviar o áudio' : 'Falar com o assistente'}
      >
        {sending ? (
          <ActivityIndicator color={colors.canvas} />
        ) : recording ? (
          <ArrowUp size={30} color={colors.onDanger} strokeWidth={2} />
        ) : (
          <Mic size={28} color={colors.canvas} strokeWidth={1.6} />
        )}
      </Pressable>
    </View>
  );
}

/** O anel de 1 px que se espalha a cada 1,4 s enquanto grava. Com "Reduzir movimento", fica parado. */
function PulseRing() {
  const reduceMotion = useReducedMotion();
  const progress = useSharedValue(0);

  useEffect(() => {
    if (!reduceMotion) {
      progress.set(withRepeat(withTiming(1, { duration: 1400, easing: Easing.out(Easing.ease) }), -1));
    }
  }, [progress, reduceMotion]);

  const animated = useAnimatedStyle(() => ({
    opacity: 0.8 * (1 - progress.get()),
    transform: [{ scale: 1 + 0.5 * progress.get() }],
  }));

  return <Animated.View style={[styles.ring, animated]} />;
}

const MIC_SIZE = 72;

const styles = StyleSheet.create({
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingTop: space.sm },
  greeting: { marginTop: space.xs },
  ledger: { marginTop: space.lg },
  flex: { flex: 1, gap: space.xs },
  notice: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    marginTop: space.lg,
    paddingVertical: space.sm,
    paddingLeft: space.md,
    borderLeftWidth: 2,
  },
  noticeOverdue: { borderLeftColor: colors.danger },
  noticeToday: { borderLeftColor: colors.warn },
  pressed: { backgroundColor: colors.pressed },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', paddingVertical: space.sm },
  // O microfone e o que vem embaixo dele: o que o tour destaca no primeiro passo.
  micGroup: { alignItems: 'center', gap: space.xs },
  micArea: { width: MIC_SIZE, height: MIC_SIZE, alignItems: 'center', justifyContent: 'center', marginBottom: space.sm },
  mic: {
    width: MIC_SIZE,
    height: MIC_SIZE,
    borderRadius: radius.pill,
    backgroundColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  micRecording: { backgroundColor: colors.danger },
  micPressed: { backgroundColor: colors.inkPressed, transform: [{ translateY: 1 }] },
  micRecordingPressed: { backgroundColor: colors.dangerPressed, transform: [{ translateY: 1 }] },
  ring: {
    position: 'absolute',
    width: MIC_SIZE,
    height: MIC_SIZE,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: colors.danger,
  },
  tabular: { fontVariant: ['tabular-nums'] },
  centered: { alignSelf: 'center', marginTop: space.sm },
  todayHeader: { marginTop: space.sm },
  emptyToday: { paddingVertical: space.lg },
});
