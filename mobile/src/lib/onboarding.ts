import type { Href } from 'expo-router';

import type { Onboarding, OnboardingStep, OnboardingStepKey } from '@/api/types';

export type StepCopy = {
  title: string;
  // Embaixo do título, enquanto o passo está a fazer.
  hint: string;
  // A folha que abre ao tocar: o porquê do passo, em duas ou três linhas.
  explain: string;
  // O botão principal da folha e a tela que ele abre (sempre uma que já existe).
  action: string;
  route: Href;
  // Um segundo caminho, quando há ("Falar com o assistente").
  secondary?: { label: string; route: Href };
  // Ignorar o passo, com o motivo dito pela pessoa. Sem ele, o passo não se ignora.
  skipLabel?: string;
};

/** Os textos e os destinos de cada passo (guia 1.7, Primeiros passos). */
export const STEP_COPY: Record<OnboardingStepKey, StepCopy> = {
  household_name: {
    title: 'Dar nome à casa',
    hint: 'Aparece nas Configurações e nos convites',
    explain: 'O nome aparece nas Configurações e nos convites para quem mora com você, como "Casa da Marina" ou "Apê da Vila".',
    action: 'Dar nome',
    route: { pathname: '/settings/household-form', params: { mode: 'rename' } },
  },
  first_transaction: {
    title: 'Primeiro lançamento',
    hint: 'Fale, escreva no Chat ou lance à mão',
    explain: 'Fale no microfone do Início ("gastei 30 no mercado"), escreva no Chat ou use o formulário. Nada é gravado antes de você conferir.',
    action: 'Lançar à mão',
    route: '/transactions/new',
    secondary: { label: 'Escrever no Chat', route: '/chat' },
  },
  cards: {
    title: 'Seus cartões',
    hint: 'As compras caem na fatura certa',
    explain: 'Cadastre o cartão de crédito com o dia do fechamento e do vencimento. As compras caem na fatura certa, e o app avisa antes de vencer.',
    action: 'Cadastrar cartão',
    route: '/cards/new',
    skipLabel: 'Não uso cartão de crédito',
  },
  recurring_income: {
    title: 'Salário e receitas fixas',
    hint: 'Entram sozinhos todo mês',
    explain: 'O que entra todo mês no mesmo dia, como o salário, é lançado sozinho. Assim o saldo do mês já começa certo.',
    action: 'Cadastrar receita fixa',
    route: '/recurring-incomes/new',
    skipLabel: 'Minha renda varia',
  },
  recurring_bills: {
    title: 'Contas fixas',
    hint: 'Aviso na véspera e no dia',
    explain: 'Luz, aluguel, internet: o app avisa na véspera e no dia do vencimento e, no débito automático, lança sozinho.',
    action: 'Cadastrar conta fixa',
    route: '/bills/new',
    skipLabel: 'Não tenho contas fixas',
  },
  categories: {
    title: 'Conferir as categorias',
    hint: 'As principais já estão prontas',
    explain: 'A casa já começa com categorias de despesa e de receita. Renomeie, arquive ou crie as que fizerem falta e toque em "Estão boas".',
    action: 'Ver as categorias',
    route: { pathname: '/categories', params: { review: '1' } },
  },
  invite: {
    title: 'Chamar quem mora com você',
    hint: 'Cada pessoa com o seu login',
    explain: 'Convide quem divide as contas: cada pessoa entra com o seu login e escolhe o que é da casa e o que é só dela.',
    action: 'Convidar',
    route: '/settings/household',
    skipLabel: 'Só eu uso o app',
  },
};

/** O primeiro passo ainda a fazer, na ordem da tela. */
export function nextStep(state: Onboarding): OnboardingStep | undefined {
  return state.steps.find((step) => step.status === 'pending');
}

/** Quanto já foi feito, de 0 a 100. Ignorar não conta como feito. */
export function progressPercent(state: Onboarding): number {
  return state.total === 0 ? 0 : Math.round((state.done * 100) / state.total);
}

/** "2 de 7 feitos · 1 ignorado". */
export function progressLabel(state: Onboarding): string {
  const done = `${state.done} de ${state.total} ${state.done === 1 ? 'feito' : 'feitos'}`;

  if (state.skipped === 0) {
    return done;
  }

  return `${done} · ${state.skipped} ${state.skipped === 1 ? 'ignorado' : 'ignorados'}`;
}

/** Se a linha do Início aparece: há passo a fazer e a pessoa não a escondeu. */
export function showsHomeBlock(state: Onboarding): boolean {
  return !state.hidden && state.steps.some((step) => step.status === 'pending');
}

/** O título do passo no meio da frase ("Próximo: seus cartões"). */
export function lowerFirst(text: string): string {
  return text.charAt(0).toLowerCase() + text.slice(1);
}
