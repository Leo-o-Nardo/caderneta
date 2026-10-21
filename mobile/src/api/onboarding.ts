import { queryOptions, useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

import { keys } from './keys';
import type { Onboarding, OnboardingChange } from './types';

/** Os primeiros passos na casa atual: o que foi visto, o checklist calculado e o ponto do tour. */
export const onboardingQuery = queryOptions({ queryKey: keys.onboarding, queryFn: () => api<Onboarding>('/onboarding') });

export function useOnboarding() {
  return useQuery(onboardingQuery);
}

/** Guarda uma escolha. A resposta já é o estado novo, que entra direto no cache. */
export async function updateOnboarding(change: OnboardingChange): Promise<Onboarding> {
  const state = await api<Onboarding>('/onboarding', { method: 'PATCH', body: change });
  queryClient.setQueryData(keys.onboarding, state);

  return state;
}
