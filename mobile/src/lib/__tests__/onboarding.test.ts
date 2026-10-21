import type { Onboarding, OnboardingStep } from '@/api/types';

import { lowerFirst, nextStep, progressLabel, progressPercent, showsHomeBlock, STEP_COPY } from '../onboarding';

function step(key: OnboardingStep['key'], status: OnboardingStep['status']): OnboardingStep {
  return { key, status, detail: status === 'done' ? 'feito' : null, skippable: true };
}

function state(steps: OnboardingStep[], extra: Partial<Onboarding> = {}): Onboarding {
  return {
    welcome_seen: true,
    joined: false,
    hidden: false,
    tour: { step: null, completed: false },
    steps,
    done: steps.filter((item) => item.status === 'done').length,
    skipped: steps.filter((item) => item.status === 'skipped').length,
    total: steps.length,
    ...extra,
  };
}

const sample = state([
  step('household_name', 'done'),
  step('first_transaction', 'done'),
  step('cards', 'pending'),
  step('recurring_income', 'pending'),
  step('invite', 'skipped'),
]);

describe('nextStep', () => {
  it('is the first step still to do, in screen order', () => {
    expect(nextStep(sample)?.key).toBe('cards');
  });

  it('skips the ignored steps and is empty when nothing is left', () => {
    expect(nextStep(state([step('cards', 'skipped'), step('categories', 'done')]))).toBeUndefined();
  });
});

describe('progress', () => {
  it('counts only what was done', () => {
    expect(progressPercent(sample)).toBe(40);
    expect(progressPercent(state([]))).toBe(0);
  });

  it('says how many were done and ignored', () => {
    expect(progressLabel(sample)).toBe('2 de 5 feitos · 1 ignorado');
    expect(progressLabel(state([step('cards', 'done'), step('invite', 'pending')]))).toBe('1 de 2 feito');
  });
});

describe('showsHomeBlock', () => {
  it('shows while there is something to do', () => {
    expect(showsHomeBlock(sample)).toBe(true);
  });

  it('hides when everything is done or ignored, or when the person hid it', () => {
    expect(showsHomeBlock(state([step('cards', 'done'), step('invite', 'skipped')]))).toBe(false);
    expect(showsHomeBlock({ ...sample, hidden: true })).toBe(false);
    // O "Só leitura" não tem passos: só o tour, em Configurações.
    expect(showsHomeBlock(state([]))).toBe(false);
  });
});

describe('STEP_COPY', () => {
  it('only offers to skip the steps the API lets skip', () => {
    const skippable = Object.entries(STEP_COPY)
      .filter(([, copy]) => copy.skipLabel)
      .map(([key]) => key);

    expect(skippable).toEqual(['cards', 'recurring_income', 'recurring_bills', 'invite']);
  });

  it('reads well in the middle of a sentence', () => {
    expect(`Próximo: ${lowerFirst(STEP_COPY.cards.title)}`).toBe('Próximo: seus cartões');
  });
});
