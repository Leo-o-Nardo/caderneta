import { Fraunces_500Medium } from '@expo-google-fonts/fraunces/500Medium';
import { Fraunces_600SemiBold } from '@expo-google-fonts/fraunces/600SemiBold';
import { IBMPlexSans_400Regular } from '@expo-google-fonts/ibm-plex-sans/400Regular';
import { IBMPlexSans_500Medium } from '@expo-google-fonts/ibm-plex-sans/500Medium';
import { IBMPlexSans_600SemiBold } from '@expo-google-fonts/ibm-plex-sans/600SemiBold';
import { QueryClientProvider } from '@tanstack/react-query';
import { useFonts } from 'expo-font';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { useHomeReady } from '@/api/home';
import { OpeningSplash } from '@/components/OpeningSplash';
import { SheetHost } from '@/components/ui';
import { queryClient } from '@/lib/queryClient';
import { useOpening } from '@/stores/opening';
import { useSession } from '@/stores/session';
import { colors } from '@/theme/tokens';

// A splash do sistema fica até a abertura desenhar o primeiro quadro, igual a ela (OpeningSplash).
SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  // Só os pesos do guia, cada um pelo próprio caminho: importar o pacote inteiro levaria as 32 variações.
  const [fontsLoaded] = useFonts({
    IBMPlexSans_400Regular,
    IBMPlexSans_500Medium,
    IBMPlexSans_600SemiBold,
    Fraunces_500Medium,
    Fraunces_600SemiBold,
  });
  const status = useSession((state) => state.status);
  const ready = fontsLoaded && status !== 'loading';
  // Com login, a capa espera os dados do Início para abrir: nada de "Carregando…" embaixo dela.
  const homeReady = useHomeReady();
  // A abertura toca uma vez, ao abrir o app do zero; voltar do segundo plano não monta de novo.
  const opening = useOpening((state) => !state.done);

  useEffect(() => {
    useSession.getState().restore();
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <SafeAreaProvider>
        {/* Texto escuro na barra de status, sobre o papel. */}
        <StatusBar style="dark" />
        {/* O app carrega por baixo da abertura: quando a capa abre, a tela de verdade já está ali. */}
        {ready ? (
          <>
            {/*
              Rotas protegidas: sem login só existem as telas de entrada; com login, só o app.
              Quando a sessão muda (entrou, saiu, token expirou), o Expo Router troca de tela sozinho.
            */}
            <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.canvas } }}>
              <Stack.Protected guard={status === 'signedIn'}>
                <Stack.Screen name="(app)" />
              </Stack.Protected>
              <Stack.Protected guard={status === 'signedOut'}>
                <Stack.Screen name="(auth)" />
              </Stack.Protected>
              {/*
                O link do convite abre com ou sem login. Fica por último: quando a sessão muda, o Expo
                Router leva para a primeira tela liberada, que deve ser o app ou a entrada, nunca esta.
              */}
              <Stack.Screen name="convite/[code]" options={{ animation: 'slide_from_bottom' }} />
            </Stack>
            {/* A folha de confirmação (confirm()), por cima de qualquer tela. */}
            <SheetHost />
          </>
        ) : null}
        {opening ? <OpeningSplash ready={ready} contentReady={homeReady} onDone={useOpening.getState().finish} /> : null}
      </SafeAreaProvider>
    </QueryClientProvider>
  );
}
