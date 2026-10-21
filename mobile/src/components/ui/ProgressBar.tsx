import { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';
import Animated, { Easing, useAnimatedStyle, useReducedMotion, useSharedValue, withTiming } from 'react-native-reanimated';

import { colors, radius } from '@/theme/tokens';

type Props = {
  // De 0 a 100; acima de 100 a barra fica cheia.
  percent: number;
  color?: string;
  // Barra de comparação ("Gastos por categoria"): 3 px, sem trilho, só o comprimento conta.
  trackless?: boolean;
};

// A curva do guia: começa rápido e desacelera no fim, sem quicar.
const EASING = Easing.bezier(0.2, 0.7, 0.2, 1);

/**
 * A barra de 4 px do guia. Cresce até o valor em 450 ms e troca de cor em 200 ms (ao passar de
 * 80% ou de 100%). Com "Reduzir movimento" ligado, vai direto ao valor.
 */
export function ProgressBar({ percent, color = colors.accent, trackless = false }: Props) {
  const target = Math.max(0, Math.min(100, percent));
  const reduceMotion = useReducedMotion();
  const width = useSharedValue(reduceMotion ? target : 0);
  const fill = useSharedValue(color);

  useEffect(() => {
    width.set(reduceMotion ? target : withTiming(target, { duration: 450, easing: EASING }));
  }, [reduceMotion, target, width]);

  useEffect(() => {
    fill.set(reduceMotion ? color : withTiming(color, { duration: 200 }));
  }, [color, fill, reduceMotion]);

  const animated = useAnimatedStyle(() => ({ width: `${width.get()}%` as const, backgroundColor: fill.get() }));

  return (
    <View
      style={[styles.track, trackless && styles.trackless]}
      accessibilityRole="progressbar"
      accessibilityValue={{ min: 0, max: 100, now: Math.round(percent) }}
    >
      <Animated.View style={[styles.fill, animated]} />
    </View>
  );
}

const styles = StyleSheet.create({
  track: { height: 4, borderRadius: radius.xs, backgroundColor: colors.line, overflow: 'hidden' },
  trackless: { height: 3, backgroundColor: 'transparent' },
  fill: { height: '100%', borderRadius: radius.xs },
});
