import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, View, type LayoutChangeEvent } from 'react-native';
import Animated, {
  Easing,
  interpolate,
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withDelay,
  withTiming,
} from 'react-native-reanimated';
import Svg, { Path } from 'react-native-svg';
import { scheduleOnRN } from 'react-native-worklets';

import { markInTile, markOnCover, splashTile } from '@/lib/opening';
import { BRAND } from '@/theme/brand';
import { colors } from '@/theme/tokens';

// Os tempos aprovados no protótipo (guia 1.6, parte 07: "Abertura do app").
const GROW_MS = 1100;
const BAND_MS = 480;
// A capa começa a abrir quando o elástico já está quase fora.
const OPEN_DELAY_MS = 380;
const OPEN_MS = 1250;
const FADE_MS = 200;
// A curva do guia (sobe rápido e desacelera), a do elástico puxado e a da capa virando.
const GROW_EASING = Easing.bezier(0.2, 0.7, 0.2, 1);
const BAND_EASING = Easing.bezier(0.45, 0, 0.55, 1);
const OPEN_EASING = Easing.bezier(0.5, 0, 0.25, 1);
// A capa vira até passar do perfil (90°) e sumir; ao virar, escurece um pouco, como sob a luz.
const OPEN_ANGLE = -100;
const PERSPECTIVE = 1400;
const SHADE = 0.28;
// Quanto o elástico anda para a direita, em larguras dele, até sair da tela.
const BAND_TRAVEL = 2.4;
// Depois de crescer, a capa espera os dados da primeira tela por no máximo isso: com a rede lenta,
// abre assim mesmo, e a tela mostra o próprio "Carregando…".
const MAX_WAIT_MS = 3000;

type Props = {
  // As fontes carregaram e o login salvo foi lido: o app já está montado embaixo da capa.
  ready: boolean;
  // A primeira tela tem os dados (com login, o Início): a capa abre sobre ela completa.
  contentReady: boolean;
  // A abertura terminou (ou foi pulada): sai da tela.
  onDone: () => void;
};

/**
 * A abertura do app (guia 1.6): começa igual à splash do sistema, com o ícone parado no centro do
 * Papel. O ícone cresce até virar a capa da caderneta e, quando o app está pronto, o elástico sai
 * e a capa gira na dobradiça da esquerda, mostrando a tela de verdade embaixo. Se o app demora, a
 * capa espera parada; pelos dados da primeira tela, no máximo MAX_WAIT_MS. Um toque pula; com "Reduzir movimento", o ícone some num fade.
 */
export function OpeningSplash({ ready, contentReady, onDone }: Props) {
  const reduceMotion = useReducedMotion();
  const [screen, setScreen] = useState<{ width: number; height: number } | null>(null);
  const [grown, setGrown] = useState(false);
  const [waitedEnough, setWaitedEnough] = useState(false);
  const opened = useRef(false);
  const grow = useSharedValue(0);
  const band = useSharedValue(0);
  const turn = useSharedValue(0);
  const fade = useSharedValue(1);

  // Sem crescer (reduzir movimento), a espera é no próprio ícone da splash. Só abre depois de medir a tela,
  // que é quando a splash do sistema sai: sem isso, ela poderia ficar presa.
  const waiting = grown || reduceMotion;
  const opening = screen !== null && waiting && ready && (contentReady || waitedEnough);

  // Ao fim de uma animação (na thread dela), avisa a tela que a abertura terminou. Fica memorizada
  // porque o efeito que abre a capa depende dela.
  const finish = useCallback(
    (finished?: boolean) => {
      'worklet';
      if (finished) {
        scheduleOnRN(onDone);
      }
    },
    [onDone],
  );

  function measure(event: LayoutChangeEvent) {
    if (!screen) {
      const { width, height } = event.nativeEvent.layout;
      setScreen({ width, height });
    }
  }

  // O primeiro quadro já está desenhado, igual à splash do sistema: ela pode sair. Depois, a capa cresce.
  useEffect(() => {
    if (!screen) {
      return;
    }

    SplashScreen.hideAsync();

    if (!reduceMotion) {
      grow.set(
        withTiming(1, { duration: GROW_MS, easing: GROW_EASING }, (finished) => {
          'worklet';
          if (finished) {
            scheduleOnRN(setGrown, true);
          }
        }),
      );
    }
  }, [screen, reduceMotion, grow]);

  // A capa está parada na tela: a espera pelos dados começa a contar a partir daqui.
  useEffect(() => {
    if (screen === null || !waiting) {
      return;
    }

    const timer = setTimeout(() => setWaitedEnough(true), MAX_WAIT_MS);

    return () => clearTimeout(timer);
  }, [screen, waiting]);

  // O app e os dados ficaram prontos (ou a espera acabou) e a capa cresceu: o elástico sai e a capa abre.
  useEffect(() => {
    if (!opening || opened.current) {
      return;
    }

    opened.current = true;

    if (reduceMotion) {
      fade.set(withTiming(0, { duration: FADE_MS }, finish));
      return;
    }

    band.set(withTiming(1, { duration: BAND_MS, easing: BAND_EASING }));
    turn.set(
      withDelay(
        OPEN_DELAY_MS,
        withTiming(1, { duration: OPEN_MS, easing: OPEN_EASING }, finish),
      ),
    );
  }, [opening, reduceMotion, band, turn, fade, finish]);

  // Um toque pula, mesmo durante a espera pelos dados: com o app montado, a abertura some num fade.
  function skip() {
    if (ready && !opened.current) {
      opened.current = true;
      fade.set(withTiming(0, { duration: FADE_MS }, finish));
    }
  }

  const tile = screen ? splashTile(screen) : null;
  const markFrom = tile ? markInTile(tile.width) : null;
  const markTo = screen ? markOnCover(screen) : null;
  const tileRadius = BRAND.tileRadius * BRAND.splashSize;

  const fadeStyle = useAnimatedStyle(() => ({ opacity: fade.get() }));

  const coverStyle = useAnimatedStyle(() => {
    if (!screen || !tile) {
      return {};
    }

    const t = grow.get();

    return {
      left: interpolate(t, [0, 1], [tile.left, 0]),
      top: interpolate(t, [0, 1], [tile.top, 0]),
      width: interpolate(t, [0, 1], [tile.width, screen.width]),
      height: interpolate(t, [0, 1], [tile.height, screen.height]),
      borderRadius: interpolate(t, [0, 1], [tileRadius, 0]),
      transform: [{ perspective: PERSPECTIVE }, { rotateY: `${interpolate(turn.get(), [0, 1], [0, OPEN_ANGLE])}deg` }],
    };
  });

  const markStyle = useAnimatedStyle(() => {
    if (!markFrom || !markTo) {
      return {};
    }

    const t = grow.get();

    return {
      left: interpolate(t, [0, 1], [markFrom.left, markTo.left]),
      top: interpolate(t, [0, 1], [markFrom.top, markTo.top]),
      width: interpolate(t, [0, 1], [markFrom.width, markTo.width]),
      height: interpolate(t, [0, 1], [markFrom.height, markTo.height]),
    };
  });

  const bandStyle = useAnimatedStyle(() => {
    // A largura do elástico na capa cheia, para ele andar o bastante para sair da tela.
    const width = screen ? (screen.width * BRAND.band.width) / BRAND.grid : 0;

    return {
      opacity: 1 - band.get(),
      transform: [{ translateX: band.get() * width * BAND_TRAVEL }],
    };
  });

  const shadeStyle = useAnimatedStyle(() => ({ opacity: turn.get() * SHADE }));

  const { x, y, width, height } = BRAND.markBox;

  return (
    <Animated.View
      style={[StyleSheet.absoluteFill, fadeStyle]}
      onLayout={measure}
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
    >
      {/* Na capa cheia, a barra de status fica clara; com o ícone pequeno e ao abrir, escura sobre o Papel. */}
      <StatusBar style={grown && !opening ? 'light' : 'dark'} />

      <Pressable style={StyleSheet.absoluteFill} onPress={skip}>
        {/* O Papel atrás do ícone, como na splash do sistema. Ao abrir, sai: embaixo já está a tela do app. */}
        {!opening ? <View style={[StyleSheet.absoluteFill, styles.paper]} /> : null}

        {screen ? (
          <Animated.View style={[styles.cover, coverStyle]}>
            <Animated.View style={[styles.band, bandStyle]} />
            <Animated.View style={[styles.mark, markStyle]}>
              <Svg width="100%" height="100%" viewBox={`${x} ${y} ${width} ${height}`}>
                {BRAND.mark.map((d) => (
                  <Path key={d} d={d} fill={colors.canvas} />
                ))}
              </Svg>
            </Animated.View>
            <Animated.View style={[StyleSheet.absoluteFill, styles.shade, shadeStyle]} />
          </Animated.View>
        ) : null}
      </Pressable>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  paper: { backgroundColor: colors.canvas },
  cover: {
    position: 'absolute',
    overflow: 'hidden',
    backgroundColor: colors.accent,
    transformOrigin: 'left center',
    backfaceVisibility: 'hidden',
  },
  band: {
    position: 'absolute',
    top: 0,
    bottom: 0,
    left: `${(BRAND.band.x / BRAND.grid) * 100}%`,
    width: `${(BRAND.band.width / BRAND.grid) * 100}%`,
    backgroundColor: colors.ink,
  },
  mark: { position: 'absolute' },
  shade: { backgroundColor: colors.inkPressed, opacity: 0 },
});
