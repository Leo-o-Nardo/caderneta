import { ChartBar } from 'lucide-react-native';
import { StyleSheet, View } from 'react-native';

import type { BarChartCard as BarChartCardData } from '@/api/types';
import { Card, ProgressBar, Text } from '@/components/ui';
import { barWidths } from '@/lib/chat';
import { colors, space } from '@/theme/tokens';

import { FichaBody, FichaHeader } from './Ficha';

/**
 * O gráfico de uma consulta ("gastos por categoria"). É desenhado com os números que a ferramenta
 * devolveu, não com o texto da IA. Barras deitadas e finas, em Tinta, como no Resumo: o que conta
 * é o comprimento.
 */
export function BarChartCard({ card }: { card: BarChartCardData }) {
  const widths = barWidths(card.bars);

  return (
    <Card>
      <FichaHeader icon={ChartBar} title={card.title} />

      <FichaBody>
        <Text variant="figure">{card.total}</Text>
        <Text variant="caption" tone="muted">
          {card.subtitle}
        </Text>
      </FichaBody>

      <FichaBody divided>
        {card.bars.map((bar, index) => (
          <View key={bar.label} style={styles.bar}>
            <View style={styles.line}>
              <Text variant="label" numberOfLines={1} style={styles.label}>
                {bar.label}
              </Text>
              <Text variant="caption" tone="muted" style={styles.value}>
                {bar.value}
              </Text>
            </View>
            <ProgressBar percent={widths[index]} color={colors.ink} trackless />
          </View>
        ))}
      </FichaBody>
    </Card>
  );
}

const styles = StyleSheet.create({
  bar: { gap: space.xs },
  line: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  label: { flex: 1 },
  value: { fontVariant: ['tabular-nums'] },
});
