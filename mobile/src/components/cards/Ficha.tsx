import type { LucideIcon } from 'lucide-react-native';
import { useEffect, type ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { useAnimatedProps, useReducedMotion, useSharedValue, withDelay, withTiming } from 'react-native-reanimated';
import Svg, { Path } from 'react-native-svg';

import { Amount, Text, TextLink } from '@/components/ui';
import { colors, space, type ColorName } from '@/theme/tokens';

/*
 * As peças das fichas do chat (guia, partes 04 e 06): o topo com o ícone do tipo, as linhas, o
 * total com o traço duplo de contabilidade, o link do pé e a linha final depois da decisão.
 */

type HeaderProps = {
  // O ícone de 16 do tipo de ficha (recibo, alvo, sino…), na mesma cor do rótulo.
  icon: LucideIcon;
  title: string;
  // Muted no normal; warn ou danger quando o aviso pede atenção.
  tone?: ColorName;
  // À direita: a hora do aviso, a data ou "toque para corrigir".
  aside?: string | null;
};

export function FichaHeader({ icon: Icon, title, tone = 'muted', aside }: HeaderProps) {
  return (
    <View style={styles.header}>
      <View style={styles.title}>
        <Icon size={16} color={colors[tone]} strokeWidth={1.6} />
        <Text variant="overline" tone={tone} numberOfLines={1} style={styles.shrink}>
          {title}
        </Text>
      </View>
      {aside ? (
        <Text variant="caption" tone="muted">
          {aside}
        </Text>
      ) : null}
    </View>
  );
}

type ItemProps = {
  // O ícone da categoria (CategoryIcon), na coluna de 24.
  icon?: ReactNode;
  title: string;
  detail?: string;
  detailTone?: ColorName;
  // O valor à direita (Amount ou um texto).
  value?: ReactNode;
  // Riscado: apagado ou desfeito.
  struck?: boolean;
};

/** Uma linha da ficha: o ícone, o nome com o detalhe embaixo e o valor à direita, com a pauta em cima. */
export function FichaItem({ icon, title, detail, detailTone = 'muted', value, struck = false }: ItemProps) {
  return (
    <View style={styles.item}>
      {icon}
      <View style={styles.texts}>
        <Text variant="strong" numberOfLines={2} style={struck ? styles.struck : undefined}>
          {title}
        </Text>
        {detail ? (
          <Text variant="caption" tone={detailTone} numberOfLines={2}>
            {detail}
          </Text>
        ) : null}
      </View>
      {value}
    </View>
  );
}

/** O conteúdo solto da ficha (barra, textos, campos), com as margens de dentro dela. */
export function FichaBody({ children, divided = false }: { children: ReactNode; divided?: boolean }) {
  return <View style={[styles.body, divided && styles.divided]}>{children}</View>;
}

// A distância entre as duas linhas do traço duplo, como no livro-caixa.
const RULE_GAP = 2;

/** O total da ficha, com o traço de Tinta em cima e o traço duplo de contabilidade embaixo do valor. */
export function FichaTotal({ cents, label = 'Total' }: { cents: number; label?: string }) {
  return (
    <View style={styles.total}>
      <Text variant="strong">{label}</Text>
      <View>
        <Amount cents={cents} variant="figure" />
        <View style={styles.rule} />
        <View style={styles.rule} />
      </View>
    </View>
  );
}

/** O pé da ficha com os botões ou a linha final. */
export function FichaFooter({ children }: { children: ReactNode }) {
  return <View style={styles.footer}>{children}</View>;
}

/** O link do pé da ficha ("Ver lançamentos de Mercado"), separado por uma pauta. */
export function FichaLink({ label, onPress }: { label: string; onPress: () => void }) {
  return (
    <View style={[styles.link, styles.divided]}>
      <TextLink label={label} onPress={onPress} />
    </View>
  );
}

type ResultProps = {
  text: string;
  // ✓ em Musgo: deu certo (confirmado, paga, feito).
  done?: boolean;
  // O ícone do que houve quando não é o ✓ (cancelado, adiado, desfeito), em Lápis.
  icon?: LucideIcon;
  // Desenha o ✓ em 300 ms: só quando a decisão acabou de acontecer, não ao rolar o chat.
  animate?: boolean;
};

/** A linha final da ficha depois da decisão: "✓ Confirmado", "Cancelado", "Adiado…". */
export function FichaResult({ text, done = false, icon: Icon, animate = false }: ResultProps) {
  return (
    <View style={styles.result}>
      {done ? <DrawnCheck animate={animate} /> : Icon ? <Icon size={18} color={colors.faint} strokeWidth={1.6} /> : null}
      <Text variant="label" tone={done ? 'accent' : 'faint'} style={styles.shrink}>
        {text}
      </Text>
    </View>
  );
}

const AnimatedPath = Animated.createAnimatedComponent(Path);
// O comprimento do traço do ✓ (as duas pernas somam um pouco menos que isso).
const CHECK_LENGTH = 24;

/** O ✓ que se desenha da esquerda para a direita. Com "Reduzir movimento", aparece pronto. */
function DrawnCheck({ animate }: { animate: boolean }) {
  const reduceMotion = useReducedMotion();
  const drawing = animate && !reduceMotion;
  const offset = useSharedValue(drawing ? CHECK_LENGTH : 0);

  useEffect(() => {
    if (drawing) {
      offset.set(withDelay(100, withTiming(0, { duration: 300 })));
    }
  }, [drawing, offset]);

  const animatedProps = useAnimatedProps(() => ({ strokeDashoffset: offset.get() }));

  return (
    <Svg width={18} height={18} viewBox="0 0 24 24">
      <AnimatedPath
        d="M4 12.5l5 5L20 6.5"
        stroke={colors.accent}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
        fill="none"
        strokeDasharray={CHECK_LENGTH}
        animatedProps={animatedProps}
      />
    </Svg>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: space.md,
    paddingTop: space.md,
    paddingHorizontal: space.lg,
    paddingBottom: space.sm,
  },
  title: { flexDirection: 'row', alignItems: 'center', gap: space.sm, flexShrink: 1 },
  shrink: { flexShrink: 1 },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: space.md,
    paddingVertical: space.md,
    paddingHorizontal: space.lg,
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  texts: { flex: 1, gap: space.xs },
  struck: { textDecorationLine: 'line-through' },
  body: { gap: space.sm, paddingHorizontal: space.lg, paddingBottom: space.lg, paddingTop: space.xs },
  divided: { borderTopWidth: 1, borderTopColor: colors.line, paddingTop: space.md },
  total: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: space.md,
    paddingHorizontal: space.lg,
    borderTopWidth: 1,
    borderTopColor: colors.ink,
  },
  rule: { height: 1, marginTop: RULE_GAP, backgroundColor: colors.ink },
  footer: { gap: space.sm, paddingTop: space.xs, paddingHorizontal: space.lg, paddingBottom: space.lg },
  link: { paddingVertical: space.md, paddingHorizontal: space.lg },
  result: { flexDirection: 'row', alignItems: 'center', gap: space.sm, paddingTop: space.sm },
});
