import { router } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { AccessibilityInfo, BackHandler, Pressable, StyleSheet, useWindowDimensions, View } from 'react-native';
import Animated, { Easing, useAnimatedProps, useReducedMotion, useSharedValue, withTiming } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Path } from 'react-native-svg';

import { updateOnboarding, useOnboarding } from '@/api/onboarding';
import { Button, Text } from '@/components/ui';
import { roundedRectPath, sameRect, tipPlacement, type Rect } from '@/lib/tour';
import { useTour } from '@/stores/tour';
import { colors, radius, space, withAlpha } from '@/theme/tokens';

import { tourTarget } from './TourTarget';

const AnimatedPath = Animated.createAnimatedComponent(Path);

// A folga em volta do elemento, o canto do recorte e a distância entre o recorte e a ficha.
const PAD = 8;
const HOLE_RADIUS = 10;
const GAP = space.lg;
// O recorte troca de lugar com a curva do guia; com "Reduzir movimento", vai direto.
const MOVE_MS = 280;
const EASING = Easing.bezier(0.2, 0.7, 0.2, 1);
// Quanto esperar o elemento aparecer depois de trocar de aba, e de quanto em quanto tentar medir.
const FIRST_TRY_MS = 250;
const RETRY_MS = 100;
const MAX_TRIES = 15;
// Depois de achar o elemento parado, uma última conferência: se ele mudou de lugar, o recorte vai junto.
const SETTLE_CHECK_MS = 500;
// A altura da ficha antes de ela ser medida, só para a primeira posição.
const TIP_ESTIMATE = 220;

/**
 * O tour (guia 1.7): escurece a tela com Tinta a 72%, deixa um recorte em volta do elemento de
 * verdade (TourTarget) e explica numa ficha, com "Voltar", "Próximo" e "Sair". Troca de aba sozinho,
 * guarda o passo na API para retomar depois e pula o passo cujo elemento não está na tela. Toques
 * fora da ficha não fazem nada; o "voltar" do Android volta um passo.
 */
export function TourOverlay() {
  const { active, steps, index, direction, go, exit } = useTour();
  const step = active ? steps[index] : undefined;
  const { width, height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const reduceMotion = useReducedMotion();
  const onboarding = useOnboarding();
  const canSetUp = (onboarding.data?.total ?? 0) > 0;

  // O elemento medido, com o passo a que pertence: o de um passo anterior não vale para o atual.
  const [found, setFound] = useState<{ id: string; rect: Rect } | null>(null);
  const [tipHeight, setTipHeight] = useState(0);
  const overlay = useRef<View>(null);
  // Onde a camada começa na janela: as medidas dos elementos são da janela inteira.
  const origin = useRef({ x: 0, y: 0 });
  const previousRoute = useRef<string | null>(null);

  const holeX = useSharedValue(0);
  const holeY = useSharedValue(0);
  const holeW = useSharedValue(0);
  const holeH = useSharedValue(0);

  // A cada passo: troca de aba, guarda o ponto, avisa o leitor de tela e procura o elemento.
  useEffect(() => {
    if (!step) {
      return;
    }

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    const target = step.target;

    // Em outra aba (ou sem elemento), o recorte antigo some na hora em vez de passear pela tela nova.
    if (!target || step.route !== previousRoute.current) {
      holeW.set(0);
      holeH.set(0);
    }

    previousRoute.current = step.route;

    if (step.route) {
      router.navigate(step.route);
    }

    if (target) {
      updateOnboarding({ tour_step: index }).catch(() => undefined);
    }

    AccessibilityInfo.announceForAccessibility(`Passo ${index + 1} de ${steps.length}. ${step.title}`);

    if (!target) {
      return () => {
        cancelled = true;
      };
    }

    let tries = 0;
    let last: Rect | null = null;
    let settled = false;
    const stepId = step.id;

    function measure(id: string) {
      const node = tourTarget(id);

      if (!node) {
        retry(id);

        return;
      }

      node.measureInWindow((x, y, w, h) => {
        if (cancelled) {
          return;
        }

        if (w <= 0 || h <= 0) {
          retry(id);

          return;
        }

        const rect = { x: x - origin.current.x, y: y - origin.current.y, width: w, height: h };

        // A aba que acabou de abrir ainda se ajeita (a margem do topo entra depois do primeiro
        // desenho): só vale a medida que se repete. Depois, confere mais uma vez, caso algo mude.
        if (last && sameRect(last, rect)) {
          setFound({ id: stepId, rect });

          if (!settled) {
            settled = true;
            timer = setTimeout(() => measure(id), SETTLE_CHECK_MS);
          }

          return;
        }

        last = rect;
        retry(id);
      });
    }

    function retry(id: string) {
      tries += 1;

      if (tries > MAX_TRIES) {
        // Mediu, mas nunca parou: fica com a última medida.
        if (last) {
          setFound({ id: stepId, rect: last });

          return;
        }

        // O elemento não apareceu (o papel da pessoa não mostra, a tela mudou): pula o passo.
        const next = index + direction;
        go(next < 0 ? index + 1 : next);

        return;
      }

      timer = setTimeout(() => measure(id), RETRY_MS);
    }

    timer = setTimeout(() => measure(target), FIRST_TRY_MS);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [step, index, steps.length, direction, go, holeW, holeH]);

  // O "voltar" do Android volta um passo; no primeiro, sai.
  useEffect(() => {
    if (!active) {
      return;
    }

    const subscription = BackHandler.addEventListener('hardwareBackPress', () => {
      if (index > 0) {
        go(index - 1);
      } else {
        exit();
      }

      return true;
    });

    return () => subscription.remove();
  }, [active, index, go, exit]);

  // O recorte, com a folga em volta e sem passar das bordas da tela.
  const raw = step && found?.id === step.id ? found.rect : null;
  const left = raw ? Math.max(space.xs, raw.x - PAD) : 0;
  const top = raw ? Math.max(space.xs, raw.y - PAD) : 0;
  const holeWidth = raw ? Math.min(width - space.xs, raw.x + raw.width + PAD) - left : 0;
  const holeHeight = raw ? Math.min(height - space.xs, raw.y + raw.height + PAD) - top : 0;
  const hasHole = raw !== null;

  // Leva o recorte ao elemento: anda junto entre passos da mesma aba, aparece direto numa aba nova.
  useEffect(() => {
    if (!hasHole) {
      return;
    }

    const instant = reduceMotion || holeW.get() === 0;
    const to = (value: number) => (instant ? value : withTiming(value, { duration: MOVE_MS, easing: EASING }));

    holeX.set(to(left));
    holeY.set(to(top));
    holeW.set(to(holeWidth));
    holeH.set(to(holeHeight));
  }, [hasHole, left, top, holeWidth, holeHeight, reduceMotion, holeX, holeY, holeW, holeH]);

  const scrim = useAnimatedProps(() => {
    const outside = `M0 0H${width}V${height}H0Z`;
    const hole =
      holeW.get() > 0 ? roundedRectPath({ x: holeX.get(), y: holeY.get(), width: holeW.get(), height: holeH.get() }, HOLE_RADIUS) : '';

    return { d: outside + hole };
  });

  if (!active || !step) {
    return null;
  }

  const isEnd = step.target === null;
  const showTip = isEnd || hasHole;
  const measuredTip = tipHeight || TIP_ESTIMATE;
  const placement = hasHole
    ? tipPlacement({ x: left, y: top, width: holeWidth, height: holeHeight }, measuredTip, height, GAP, insets.bottom)
    : { top: Math.max(GAP, (height - measuredTip) / 2), side: 'none' as const };
  // A seta aponta para o meio do recorte, sem sair da ficha.
  const arrowLeft = Math.min(Math.max(left + holeWidth / 2 - space.lg - 7, space.lg), width - space.lg * 2 - space.xl);

  function finish(openSteps: boolean) {
    updateOnboarding({ tour_completed: true }).catch(() => undefined);
    exit();

    if (openSteps) {
      router.push('/onboarding');
    }
  }

  return (
    <View
      ref={overlay}
      style={StyleSheet.absoluteFill}
      onLayout={() =>
        overlay.current?.measureInWindow((x, y) => {
          origin.current = { x, y };
        })
      }
      accessibilityViewIsModal
    >
      {/* Segura os toques: durante o tour, o app embaixo não reage. */}
      <Pressable style={StyleSheet.absoluteFill} accessible={false} onPress={() => undefined} />

      <Svg style={StyleSheet.absoluteFill} width={width} height={height} pointerEvents="none">
        <AnimatedPath animatedProps={scrim} fill={withAlpha(colors.ink, 0.72)} fillRule="evenodd" />
      </Svg>

      {showTip ? (
        <View
          style={[styles.tip, { top: placement.top, opacity: tipHeight ? 1 : 0 }]}
          onLayout={(event) => setTipHeight(event.nativeEvent.layout.height)}
        >
          {placement.side !== 'none' ? (
            <View style={[styles.arrow, placement.side === 'below' ? styles.arrowUp : styles.arrowDown, { left: arrowLeft }]} />
          ) : null}

          <View style={styles.tipTop}>
            <View style={styles.dots} accessible accessibilityLabel={`Passo ${index + 1} de ${steps.length}`}>
              {steps.map((item, position) => (
                <View key={item.id} style={[styles.dot, position <= index && styles.dotOn]} />
              ))}
            </View>
            {isEnd ? null : (
              <Pressable onPress={exit} hitSlop={12} accessibilityRole="button" accessibilityLabel="Sair do tour">
                <Text variant="label" tone="muted">
                  Sair
                </Text>
              </Pressable>
            )}
          </View>

          <Text variant="subtitle" accessibilityRole="header">
            {step.title}
          </Text>
          <Text tone="muted">{step.body}</Text>

          <View style={styles.buttons}>
            {isEnd ? (
              <>
                {canSetUp ? (
                  <Button title="Primeiros passos" variant="secondary" size="sm" onPress={() => finish(true)} style={styles.grow} />
                ) : null}
                <Button title="Concluir" size="sm" onPress={() => finish(false)} style={styles.grow} />
              </>
            ) : (
              <>
                <Button title="Voltar" variant="secondary" size="sm" disabled={index === 0} onPress={() => go(index - 1)} style={styles.grow} />
                <Button title="Próximo" size="sm" onPress={() => go(index + 1)} style={styles.grow} />
              </>
            )}
          </View>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  tip: {
    position: 'absolute',
    left: space.lg,
    right: space.lg,
    gap: space.sm,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    paddingHorizontal: space.lg,
    paddingTop: space.md,
    paddingBottom: space.lg,
  },
  // A seta: um quadrado da cor da ficha, girado, metade para fora dela.
  arrow: { position: 'absolute', width: 14, height: 14, backgroundColor: colors.surface, transform: [{ rotate: '45deg' }] },
  arrowUp: { top: -7 },
  arrowDown: { bottom: -7 },
  tipTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', minHeight: 24 },
  dots: { flexDirection: 'row', gap: space.xs },
  dot: { width: 14, height: 2, borderRadius: radius.xs, backgroundColor: colors.line },
  dotOn: { backgroundColor: colors.ink },
  buttons: { flexDirection: 'row', gap: space.sm, marginTop: space.xs },
  grow: { flex: 1 },
});
