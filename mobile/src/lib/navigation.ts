import { router } from 'expo-router';

import { invalidateFinance } from '@/api/keys';

/**
 * Depois de salvar ou apagar: fecha a tela primeiro e só depois atualiza os dados, para a tela
 * que está fechando não buscar de novo à toa (ou mostrar "não encontrado" do que foi apagado).
 */
export function closeAndRefresh(): void {
  router.back();
  invalidateFinance();
}
