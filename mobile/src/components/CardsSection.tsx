import { router } from 'expo-router';
import { CreditCard } from 'lucide-react-native';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { useCards } from '@/api/cards';
import type { Card as CardModel } from '@/api/types';
import { Amount, Button, EmptyState, ProgressBar, Text } from '@/components/ui';
import { monthName, shortDate } from '@/lib/dates';
import { formatCents } from '@/lib/money';
import { colors, space } from '@/theme/tokens';

/** Os cartões da casa, cada um com a fatura em aberto e o limite em uso. Toque abre a fatura. */
export function CardsSection() {
  const cards = useCards();

  if (cards.isPending) {
    return <ActivityIndicator color={colors.accent} style={styles.status} />;
  }

  if (cards.isError) {
    return (
      <Text tone="danger" style={styles.status}>
        {cards.error.message}
      </Text>
    );
  }

  if (cards.data.length === 0) {
    return (
      <EmptyState
        icon={CreditCard}
        title="Nenhum cartão ainda"
        description="Cadastre o cartão com o dia de fechamento e o app diz em qual fatura cai cada compra."
        action={<Button title="Cadastrar cartão" onPress={() => router.push('/cards/new')} />}
      />
    );
  }

  return (
    <View>
      {cards.data.map((card) => (
        <CardItem key={card.id} card={card} />
      ))}
    </View>
  );
}

/**
 * Um cartão como bloco da caderneta, sem caixa: o cartão na cor dele, a fatura em aberto e o
 * limite em uso (a barra fica de Atenção a partir de 70% e Tijolo a partir de 90%).
 */
function CardItem({ card }: { card: CardModel }) {
  const invoice = card.current_invoice;
  const usedPercent = card.limit_cents ? (card.limit_used_cents / card.limit_cents) * 100 : 0;
  const usedColor = usedPercent >= 90 ? colors.danger : usedPercent >= 70 ? colors.warn : colors.accent;

  return (
    <Pressable
      onPress={() => router.push({ pathname: '/cards/[id]', params: { id: String(card.id) } })}
      style={({ pressed }) => [styles.card, pressed && styles.pressed]}
      accessibilityRole="button"
    >
      <View style={styles.top}>
        <CreditCard size={20} color={card.color} strokeWidth={1.6} />
        <Text variant="strong" numberOfLines={1} style={styles.flex}>
          {card.name}
        </Text>
        <Text variant="caption" tone="muted">
          {card.is_mine ? (card.is_default ? 'seu · padrão' : 'seu') : card.owner.name}
        </Text>
      </View>

      <View style={styles.invoice}>
        <Text variant="caption" tone="muted">
          Fatura de {monthName(invoice.month)}
        </Text>
        <Amount cents={invoice.total_cents} variant="figure" />
        <Text variant="caption" tone="muted">
          fecha {shortDate(invoice.closes_on)} · vence {shortDate(invoice.due_on)}
        </Text>
      </View>

      {card.limit_cents ? (
        <View style={styles.limit}>
          <ProgressBar percent={usedPercent} color={usedColor} />
          <Text variant="caption" tone="muted">
            {formatCents(card.limit_used_cents)} de {formatCents(card.limit_cents)} do limite
          </Text>
        </View>
      ) : null}
    </Pressable>
  );
}

// A fatura e o limite começam embaixo do nome, depois do ícone de 20 e do espaço até o nome.
const NAME_INSET = 20 + space.sm;

const styles = StyleSheet.create({
  status: { marginTop: space.xxl },
  card: { gap: space.md, paddingVertical: space.lg, borderBottomWidth: 1, borderBottomColor: colors.line },
  pressed: { backgroundColor: colors.pressed },
  top: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  flex: { flex: 1 },
  invoice: { gap: space.xs, paddingLeft: NAME_INSET },
  limit: { gap: space.sm, paddingLeft: NAME_INSET },
});
