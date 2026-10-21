import { create } from 'zustand';

type DraftsState = {
  // Por id da ação: o que a pessoa já mudou no card (valor, categoria, pagamento…).
  drafts: Record<string, unknown>;
  set: (actionId: string, draft: unknown) => void;
  clear: (actionId: string) => void;
};

/**
 * As edições feitas nos cards do chat. Ficam fora do card porque a lista do chat recicla os
 * componentes ao rolar: o que estivesse só dentro dele se perderia.
 */
export const useActionDrafts = create<DraftsState>((set) => ({
  drafts: {},

  set: (actionId, draft) => set((state) => ({ drafts: { ...state.drafts, [actionId]: draft } })),

  clear: (actionId) =>
    set((state) => {
      const drafts = { ...state.drafts };
      delete drafts[actionId];

      return { drafts };
    }),
}));

/**
 * O rascunho de uma ação: o que já foi editado ou, sem edição, o que $initial devolve (o que veio
 * no card). Devolve o valor e como trocá-lo.
 */
export function useDraft<T>(actionId: string, initial: () => T): [T, (next: T) => void] {
  const stored = useActionDrafts((state) => state.drafts[actionId]) as T | undefined;

  return [stored ?? initial(), (next: T) => useActionDrafts.getState().set(actionId, next)];
}
