import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';

const TOKEN_KEY = 'auth_token';

type SessionState = {
  // "loading" só enquanto o token salvo é lido, na abertura do app.
  status: 'loading' | 'signedIn' | 'signedOut';
  token: string | null;
  restore: () => Promise<void>;
  signIn: (token: string) => Promise<void>;
  clear: () => Promise<void>;
};

/**
 * A sessão do aparelho: só o token, guardado no SecureStore (criptografado pelo sistema). Os dados
 * do usuário vêm da API (/me) pelo cache do TanStack Query, como qualquer outro dado.
 */
export const useSession = create<SessionState>((set) => ({
  status: 'loading',
  token: null,

  async restore() {
    const token = await SecureStore.getItemAsync(TOKEN_KEY);
    set({ token, status: token ? 'signedIn' : 'signedOut' });
  },

  async signIn(token) {
    await SecureStore.setItemAsync(TOKEN_KEY, token);
    set({ token, status: 'signedIn' });
  },

  async clear() {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    set({ token: null, status: 'signedOut' });
  },
}));
