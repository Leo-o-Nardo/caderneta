import { create } from 'zustand';

type OpeningState = {
  // Se a abertura (OpeningSplash) já terminou: a capa abriu e o app está na tela.
  done: boolean;
  finish: () => void;
};

/**
 * A abertura do app, que toca uma vez ao abrir do zero. Uma folha que aparecesse no meio dela (como
 * a das notificações, no Início) podia ser engolida pela transição: quem abre folha sozinho espera.
 */
export const useOpening = create<OpeningState>((set) => ({
  done: false,
  finish: () => set({ done: true }),
}));
