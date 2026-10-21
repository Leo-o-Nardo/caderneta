import { focusManager, QueryClient } from '@tanstack/react-query';
import { AppState } from 'react-native';

import { ApiError } from './apiError';

/**
 * Cache dos dados da API. Erros de validação, permissão ou "não encontrado" não adianta repetir;
 * falta de conexão e erro do servidor são tentados de novo.
 */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: (failureCount, error) => isRetryable(error) && failureCount < 2,
    },
  },
});

function isRetryable(error: unknown): boolean {
  return !(error instanceof ApiError) || error.status === 0 || error.status >= 500;
}

// Ao voltar para o app, as telas abertas buscam os dados de novo (o "foco" do navegador, no celular).
focusManager.setEventListener((handleFocus) => {
  const subscription = AppState.addEventListener('change', (state) => handleFocus(state === 'active'));

  return () => subscription.remove();
});
