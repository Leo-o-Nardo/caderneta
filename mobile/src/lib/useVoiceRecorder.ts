import {
  AudioModule,
  RecordingPresets,
  setAudioModeAsync,
  useAudioRecorder,
  useAudioRecorderState,
  type RecordingOptions,
} from 'expo-audio';
import { useEffect, useEffectEvent, useRef } from 'react';
import { Alert } from 'react-native';

import type { VoiceRecording } from '@/api/assistant';

// Fala não precisa de qualidade de música: mono, 16 kHz e 32 kbps dão ~250 KB por minuto, que
// sobem rápido até no 4G. O arquivo sai em .m4a (AAC), que o Gemini aceita direto.
const SPEECH: RecordingOptions = { ...RecordingPresets.HIGH_QUALITY, sampleRate: 16000, numberOfChannels: 1, bitRate: 32000 };

// Os mesmos limites da API: até 60 s; menos de meio segundo é toque sem querer.
export const MAX_VOICE_MS = 60_000;
const MIN_VOICE_MS = 500;

/**
 * Grava a voz para o assistente: start() começa (pedindo o microfone na primeira vez), finish()
 * para e devolve a gravação, cancel() descarta. Aos 60 s a gravação para sozinha e vai para onLimit.
 */
export function useVoiceRecorder(onLimit: (recording: VoiceRecording) => void) {
  const recorder = useAudioRecorder(SPEECH);
  const state = useAudioRecorderState(recorder, 200);
  const limitHandled = useRef(false);

  async function start(): Promise<boolean> {
    const permission = await AudioModule.requestRecordingPermissionsAsync();

    if (!permission.granted) {
      Alert.alert('Microfone bloqueado', 'Para falar com o assistente, libere o microfone para o app nas configurações do celular.');

      return false;
    }

    limitHandled.current = false;
    await setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });
    await recorder.prepareToRecordAsync();
    recorder.record();

    return true;
  }

  /** Para e devolve a gravação; null se foi curta demais para ter fala. */
  async function finish(): Promise<VoiceRecording | null> {
    const durationMs = Math.min(state.durationMillis, MAX_VOICE_MS);

    await recorder.stop();
    await setAudioModeAsync({ allowsRecording: false });

    if (!recorder.uri || durationMs < MIN_VOICE_MS) {
      return null;
    }

    return { uri: recorder.uri, durationMs };
  }

  async function cancel(): Promise<void> {
    await recorder.stop();
    await setAudioModeAsync({ allowsRecording: false });
  }

  // Chegou a 60 s: para sozinho e entrega a gravação (uma vez só). O useEffectEvent deixa o efeito
  // usar a versão mais nova de finish e onLimit sem rodar de novo a cada vez que a tela redesenha.
  const reachedLimit = state.isRecording && state.durationMillis >= MAX_VOICE_MS;

  const stopAtLimit = useEffectEvent(() => {
    finish().then((recording) => {
      if (recording) {
        onLimit(recording);
      }
    });
  });

  useEffect(() => {
    if (!reachedLimit || limitHandled.current) {
      return;
    }

    limitHandled.current = true;
    stopAtLimit();
  }, [reachedLimit]);

  return { isRecording: state.isRecording, durationMs: state.durationMillis, start, finish, cancel };
}
