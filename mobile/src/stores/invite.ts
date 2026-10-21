import { create } from 'zustand';

type InviteState = {
  // O código do convite aberto antes do login, esperando a pessoa entrar na conta.
  pendingCode: string | null;
  setPendingCode: (code: string | null) => void;
};

/**
 * Quem abre um convite sem estar logado e escolhe "Já tenho conta" passa pela tela de entrar. O
 * código fica guardado aqui (só na memória) e, depois do login, o Início abre o convite de novo.
 */
export const useInvite = create<InviteState>((set) => ({
  pendingCode: null,
  setPendingCode: (code) => set({ pendingCode: code }),
}));
