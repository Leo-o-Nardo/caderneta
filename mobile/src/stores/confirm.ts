import { create } from 'zustand';

export type ConfirmRequest = {
  title: string;
  // O que acontece com os números ("Sai dos totais de outubro e do orçamento de Transporte").
  message?: string;
  // O que o botão faz, com o verbo ("Apagar gasto"), e não só "OK".
  confirmLabel: string;
  cancelLabel?: string;
  // Apagar, sair, limpar: o botão principal fica Tijolo.
  destructive?: boolean;
};

type ConfirmState = {
  request: ConfirmRequest | null;
  resolve: ((confirmed: boolean) => void) | null;
  answer: (confirmed: boolean) => void;
};

/** A pergunta aberta na folha de confirmação (SheetHost, montado uma vez no _layout). */
export const useConfirm = create<ConfirmState>((set, get) => ({
  request: null,
  resolve: null,
  answer: (confirmed) => {
    get().resolve?.(confirmed);
    set({ request: null, resolve: null });
  },
}));

/**
 * Pergunta antes de uma ação que não dá para desfazer, na folha do app, e responde true se a
 * pessoa confirmou: `if (await confirm({ ... })) apagar()`. Uma pergunta nova cancela a que ainda
 * estava aberta.
 */
export function confirm(request: ConfirmRequest): Promise<boolean> {
  return new Promise((resolve) => {
    useConfirm.getState().resolve?.(false);
    useConfirm.setState({ request, resolve });
  });
}
