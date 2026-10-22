import { useCanWrite } from '@/api/auth';
import { useOnboarding } from '@/api/onboarding';
import { resumeIndex, tourStepsFor } from '@/lib/tour';
import { startTour } from '@/stores/tour';

/**
 * A entrada do tour (Configurações, Primeiros passos, boas-vindas): "Fazer o tour" ou, se a pessoa
 * saiu no meio, "Continuar o tour" de onde parou. Terminar zera o ponto, e o próximo começa do 1.
 */
export function useTourEntry() {
  const canWrite = useCanWrite();
  const { data: onboarding } = useOnboarding();
  const total = tourStepsFor(canWrite).length;
  const saved = onboarding?.tour.step ?? null;
  const resuming = saved !== null && saved > 0 && resumeIndex(saved, total) === saved;

  return {
    label: resuming ? 'Continuar o tour' : 'Fazer o tour',
    detail: resuming && saved !== null ? `${saved + 1} de ${total}` : '1 min',
    start: () => startTour({ canWrite, from: resuming ? saved : null }),
  };
}
