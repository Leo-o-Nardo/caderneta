import { useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { Archive, Check, Pencil, Undo2 } from 'lucide-react-native';
import { ActivityIndicator, ScrollView, StyleSheet, View } from 'react-native';

import { useCanWrite } from '@/api/auth';
import { archiveBill, skipBillMonth, undoBillMonth, useBill } from '@/api/bills';
import { invalidateFinance } from '@/api/keys';
import type { BillDetail, BillMonth } from '@/api/types';
import { CategoryIcon } from '@/components/CategoryIcon';
import { Amount, Button, Header, IconButton, InfoRow, Screen, SectionHeader, Text } from '@/components/ui';
import { billDueLabel } from '@/lib/bills';
import { monthLabel, monthName } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { closeAndRefresh } from '@/lib/navigation';
import { confirm } from '@/stores/confirm';
import { colors, space } from '@/theme/tokens';

/** O detalhe de uma conta fixa: o mês em aberto, os dados, os últimos meses e "Arquivar". */
export default function BillScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const query = useBill(Number(id));
  const canWrite = useCanWrite();

  return (
    <Screen>
      <Header
        title={query.data?.name ?? 'Conta'}
        right={
          query.data && canWrite ? (
            <IconButton icon={Pencil} label="Editar conta" onPress={() => router.push({ pathname: '/bills/[id]/edit', params: { id } })} />
          ) : null
        }
      />

      {query.isPending ? (
        <ActivityIndicator color={colors.accent} style={styles.status} />
      ) : query.isError ? (
        <Text tone="danger" style={styles.status}>
          {query.error.message}
        </Text>
      ) : (
        <BillDetails bill={query.data} />
      )}
    </Screen>
  );
}

function BillDetails({ bill }: { bill: BillDetail }) {
  const canWrite = useCanWrite();
  const skip = useMutation({ mutationFn: (month: string) => skipBillMonth(bill.id, month), onSuccess: () => invalidateFinance() });
  const undo = useMutation({ mutationFn: (month: string) => undoBillMonth(bill.id, month), onSuccess: () => invalidateFinance() });
  const archive = useMutation({ mutationFn: () => archiveBill(bill.id), onSuccess: closeAndRefresh });

  async function confirmSkip(month: string) {
    const confirmed = await confirm({
      title: `Pular ${monthName(month)}?`,
      message: `${bill.name} sai das contas a pagar de ${monthName(month)}, sem lançar despesa.`,
      confirmLabel: `Pular ${monthName(month)}`,
    });

    if (confirmed) {
      skip.mutate(month);
    }
  }

  // Desfazer um pagamento apaga a despesa que ele lançou; desfazer um "pular" só reabre o mês.
  async function confirmUndo(item: BillMonth) {
    const month = monthName(item.month);
    const confirmed = await confirm(
      item.status === 'paid'
        ? {
            title: `Desfazer o pagamento de ${month}?`,
            message:
              `A despesa${item.amount_cents !== null ? ` de ${formatCents(item.amount_cents)}` : ''} lançada no pagamento é ` +
              `apagada e sai dos totais. ${bill.name} volta para as contas a pagar de ${month}.`,
            confirmLabel: 'Desfazer pagamento',
            destructive: true,
          }
        : {
            title: `Reabrir ${month}?`,
            message: `${bill.name} volta para as contas a pagar de ${month}.`,
            confirmLabel: `Reabrir ${month}`,
          },
    );

    if (confirmed) {
      undo.mutate(item.month);
    }
  }

  async function confirmArchive() {
    const confirmed = await confirm({
      title: `Arquivar ${bill.name}?`,
      message:
        `A conta sai da Agenda e para de avisar o vencimento${bill.autopay ? ' e de lançar o débito automático' : ''}. ` +
        'Os pagamentos já lançados continuam nos lançamentos e nos totais.',
      confirmLabel: 'Arquivar conta',
      destructive: true,
    });

    if (confirmed) {
      archive.mutate();
    }
  }

  return (
    <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.content}>
      {bill.open ? (
        <>
          <SectionHeader title="Em aberto" style={styles.firstHeader} />
          <MonthRow item={bill.open} />
          {canWrite ? (
            <View style={styles.actions}>
              <Button
                title="Paguei"
                icon={<Check size={18} color={colors.canvas} strokeWidth={1.6} />}
                style={styles.flex}
                onPress={() =>
                  bill.open && router.push({ pathname: '/bills/pay', params: { bill_id: String(bill.id), month: bill.open.month } })
                }
              />
              <Button
                title="Pular este mês"
                variant="secondary"
                style={styles.flex}
                loading={skip.isPending}
                onPress={() => bill.open && confirmSkip(bill.open.month)}
              />
            </View>
          ) : null}
        </>
      ) : null}

      <SectionHeader title="Dados" style={bill.open ? undefined : styles.firstHeader} />
      <InfoRow
        label="Categoria"
        value={
          <View style={styles.category}>
            <CategoryIcon icon={bill.category.icon} color={bill.category.color} size={20} />
            <Text variant="strong" numberOfLines={1} style={styles.shrink}>
              {bill.category.name}
            </Text>
          </View>
        }
      />
      <AmountInfo bill={bill} />
      <InfoRow label="Vence" value={`todo dia ${bill.due_day}`} />
      <InfoRow label="Débito automático" value={bill.autopay ? 'Sim' : 'Não'} />

      <SectionHeader title="Últimos meses" />
      {bill.history.map((item) => (
        <MonthRow key={item.month} item={item} onUndo={canWrite ? () => confirmUndo(item) : undefined} />
      ))}

      {canWrite ? (
        <Button
          title="Arquivar conta"
          variant="dangerLink"
          icon={<Archive size={18} color={colors.danger} strokeWidth={1.6} />}
          loading={archive.isPending}
          onPress={confirmArchive}
          style={styles.archive}
        />
      ) : null}
    </ScrollView>
  );
}

/** O valor na tabela de dados: o fixo, a estimativa (com "~") ou a frase de quando ainda não há nenhum. */
function AmountInfo({ bill }: { bill: BillDetail }) {
  if (bill.amount_varies) {
    return bill.estimate_cents ? (
      <InfoRow label="Valor estimado" value={<Amount cents={bill.estimate_cents} estimate />} />
    ) : (
      <InfoRow label="Valor" value="muda todo mês" />
    );
  }

  return bill.expected_cents ? (
    <InfoRow label="Valor" value={<Amount cents={bill.expected_cents} />} />
  ) : (
    <InfoRow label="Valor" value="informado ao pagar" />
  );
}

type MonthRowProps = {
  item: BillMonth;
  // Nos últimos meses: pago ou pulado, ganha o "Desfazer".
  onUndo?: () => void;
};

/** Um mês da conta: o mês, a situação ("paga", "atrasada há 3 dias") e o valor. */
function MonthRow({ item, onUndo }: MonthRowProps) {
  const status = billDueLabel(item);
  const settled = item.status === 'paid' || item.status === 'skipped';
  const details = [status.text, !settled && item.is_estimate ? 'valor estimado' : null].filter(Boolean).join(' · ');

  return (
    <View style={styles.row}>
      <View style={styles.texts}>
        <Text variant="strong">{monthLabel(item.month)}</Text>
        <Text variant="caption" tone={status.tone}>
          {details}
        </Text>
      </View>
      <View style={styles.right}>
        {item.amount_cents === null ? (
          <Text variant="caption" tone="muted">
            valor variável
          </Text>
        ) : (
          <Amount cents={item.amount_cents} estimate={item.is_estimate} tone={item.status === 'skipped' ? 'faint' : undefined} />
        )}
        {onUndo && settled ? (
          <Button
            title="Desfazer"
            variant="ghost"
            icon={<Undo2 size={18} color={colors.accent} strokeWidth={1.6} />}
            accessibilityLabel={`Desfazer ${monthName(item.month)}`}
            onPress={onUndo}
            style={styles.undo}
          />
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  content: { paddingBottom: space.xxl },
  firstHeader: { marginTop: space.md },
  actions: { flexDirection: 'row', gap: space.sm, marginTop: space.lg },
  flex: { flex: 1 },
  category: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  shrink: { flexShrink: 1 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  texts: { flex: 1, gap: space.xs },
  right: { alignItems: 'flex-end', gap: space.xs },
  undo: { alignSelf: 'flex-end' },
  archive: { marginTop: space.xl },
});
