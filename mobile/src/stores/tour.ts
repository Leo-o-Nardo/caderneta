import { router } from 'expo-router';
import { create } from 'zustand';

import { resumeIndex, tourStepsFor, type TourStep } from '@/lib/tour';

type TourState = {
  active: boolean;
  steps: TourStep[];
  index: number;
  // Para que lado se andou por último: um passo sem o elemento na tela é pulado nesse sentido.
  direction: 1 | -1;
  go: (index: number) => void;
  exit: () => void;
};

/** O tour aberto agora (TourOverlay, montado no layout do app). */
export const useTour = create<TourState>((set, get) => ({
  active: false,
  steps: [],
  index: 0,
  direction: 1,
  go: (index) => {
    const { steps, index: current } = get();

    if (index < 0 || index >= steps.length) {
      return;
    }

    set({ index, direction: index >= current ? 1 : -1 });
  },
  exit: () => set({ active: false }),
}));

/**
 * Começa o tour, do passo guardado (from) ou do primeiro. Fecha antes as telas abertas por cima das
 * abas (Configurações, Primeiros passos, boas-vindas): o tour acontece nas abas.
 */
export function startTour({ canWrite, from = null }: { canWrite: boolean; from?: number | null }): void {
  const steps = tourStepsFor(canWrite);

  if (router.canDismiss()) {
    router.dismissAll();
  }

  useTour.setState({ active: true, steps, index: resumeIndex(from, steps.length), direction: 1 });
}
