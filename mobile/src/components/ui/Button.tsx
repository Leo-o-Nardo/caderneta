import { useEffect, type ReactNode } from 'react';
import { Pressable, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import Animated, {
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withTiming,
} from 'react-native-reanimated';

import { colors, font, radius, space } from '@/theme/tokens';

import { Text } from './Text';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'dangerLink';

type Props = {
  title: string;
  onPress?: () => void;
  // primary: Tinta, um por tela; secondary: contorno de Tinta; ghost: link em Musgo sublinhado;
  // danger: apagar (na folha de confirmação); dangerLink: o "Apagar" no fim dos formulários, link em Tijolo.
  variant?: Variant;
  // sm: os botões lado a lado das fichas ("Feito · Adiar 1 h · Amanhã").
  size?: 'md' | 'sm';
  loading?: boolean;
  disabled?: boolean;
  icon?: ReactNode;
  // O que o leitor de tela fala, quando o título sozinho não basta ("Paguei Luz").
  accessibilityLabel?: string;
  style?: StyleProp<ViewStyle>;
};

const TEXT_COLORS: Record<Variant, string> = {
  primary: colors.canvas,
  secondary: colors.ink,
  ghost: colors.accent,
  danger: colors.onDanger,
  dangerLink: colors.danger,
};

// Os botões que são só texto sublinhado, sem fundo.
const LINKS: Variant[] = ['ghost', 'dangerLink'];

export function Button({
  title,
  onPress,
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled = false,
  icon,
  accessibilityLabel,
  style,
}: Props) {
  const inactive = disabled || loading;
  const textColor = disabled ? colors.faint : TEXT_COLORS[variant];
  const link = LINKS.includes(variant);

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? title}
      accessibilityState={{ disabled: inactive, busy: loading }}
      onPress={onPress}
      disabled={inactive}
      // Os botões de texto são baixos: a área de toque chega a 44 por fora.
      hitSlop={link ? 8 : undefined}
      style={({ pressed }) => [
        styles.base,
        size === 'sm' && styles.small,
        styles[variant],
        pressed && !inactive && [styles.pressed, PRESSED[variant]],
        disabled && !link && styles.disabled,
        style,
      ]}
    >
      {/* Carregando, o texto fica invisível no lugar (o botão não muda de largura) e os pontos vão por cima. */}
      <View style={[styles.content, loading && styles.hidden]}>
        {icon}
        <Text
          variant="label"
          style={[styles.title, size === 'sm' && styles.titleSmall, link && styles.underline, { color: textColor }]}
        >
          {title}
        </Text>
      </View>
      {loading ? <LoadingDots color={textColor} /> : null}
    </Pressable>
  );
}

/** Os três pontos que piscam em sequência enquanto o botão espera a resposta. */
function LoadingDots({ color }: { color: string }) {
  return (
    <View style={styles.dots}>
      {[0, 1, 2].map((index) => (
        <Dot key={index} color={color} delay={index * 150} />
      ))}
    </View>
  );
}

function Dot({ color, delay }: { color: string; delay: number }) {
  const reduceMotion = useReducedMotion();
  const opacity = useSharedValue(reduceMotion ? 1 : 0.25);

  useEffect(() => {
    if (!reduceMotion) {
      opacity.set(
        withDelay(delay, withRepeat(withSequence(withTiming(1, { duration: 400 }), withTiming(0.25, { duration: 600 })), -1)),
      );
    }
  }, [delay, opacity, reduceMotion]);

  const animated = useAnimatedStyle(() => ({ opacity: opacity.get() }));

  return <Animated.View style={[styles.dot, { backgroundColor: color }, animated]} />;
}

// Pressionado: um tom mais escuro e 1 px abaixo, sem onda nem brilho.
const PRESSED = StyleSheet.create({
  primary: { backgroundColor: colors.inkPressed },
  secondary: { backgroundColor: colors.pressed },
  ghost: { opacity: 0.7 },
  dangerLink: { opacity: 0.7 },
  danger: { backgroundColor: colors.dangerPressed },
});

const styles = StyleSheet.create({
  base: {
    minHeight: 48,
    borderRadius: radius.md,
    paddingHorizontal: space.xl,
    alignItems: 'center',
    justifyContent: 'center',
  },
  small: { minHeight: 40, paddingHorizontal: space.lg },
  content: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: space.sm },
  hidden: { opacity: 0 },
  primary: { backgroundColor: colors.ink },
  secondary: { backgroundColor: 'transparent', borderWidth: 1, borderColor: colors.ink },
  ghost: { backgroundColor: 'transparent', minHeight: 0, paddingHorizontal: 0, paddingVertical: space.xs, alignSelf: 'flex-start' },
  dangerLink: { backgroundColor: 'transparent', minHeight: 0, paddingHorizontal: 0, paddingVertical: space.xs, alignSelf: 'flex-start' },
  danger: { backgroundColor: colors.danger },
  pressed: { transform: [{ translateY: 1 }] },
  disabled: { backgroundColor: colors.line, borderColor: colors.line },
  title: { fontFamily: font.semibold, fontSize: 15 },
  titleSmall: { fontSize: 14 },
  underline: { fontFamily: font.medium, textDecorationLine: 'underline' },
  dots: { ...StyleSheet.absoluteFill, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: space.xs, pointerEvents: 'none' },
  dot: { width: 5, height: 5, borderRadius: radius.pill },
});
