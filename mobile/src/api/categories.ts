import { useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

import { invalidateFinance, keys } from './keys';
import { toQuery } from './query';
import type { Category, CategoryKind } from './types';

export type CategoryInput = {
  name: string;
  icon: string;
  color: string;
};

/** As categorias ativas da casa, na ordem do cadastro (as padrão primeiro). */
export function useCategories(kind?: CategoryKind) {
  return useQuery({
    queryKey: keys.categories(kind),
    queryFn: () => api<Category[]>(`/categories?${toQuery({ kind })}`),
    // Categorias quase não mudam: não precisam ser buscadas de novo a cada tela.
    staleTime: 5 * 60_000,
  });
}

export function createCategory(input: CategoryInput & { kind: CategoryKind }): Promise<Category> {
  return api('/categories', { method: 'POST', body: input });
}

/** O tipo (despesa ou receita) não muda: a API recusa, para não virar despesas antigas em receitas. */
export function updateCategory(id: number, input: CategoryInput): Promise<Category> {
  return api(`/categories/${id}`, { method: 'PATCH', body: input });
}

/**
 * Grava a nova ordem de um tipo (todas as categorias ativas dele). O cache já fica na ordem nova,
 * para a lista não piscar na ordem antiga enquanto busca de novo.
 */
export async function reorderCategories(kind: CategoryKind, categories: Category[]): Promise<void> {
  await api('/categories/order', { method: 'PUT', body: { kind, ids: categories.map((category) => category.id) } });
  queryClient.setQueryData(keys.categories(kind), categories);
}

/** Arquiva: some dos lançamentos novos, mas os antigos continuam com ela. O orçamento vai junto. */
export function archiveCategory(id: number): Promise<void> {
  return api(`/categories/${id}`, { method: 'DELETE' });
}

/**
 * Depois de criar, editar ou arquivar: além das categorias, lançamentos, resumo e orçamentos
 * mostram o nome, o ícone e a cor delas.
 */
export async function invalidateCategories(): Promise<void> {
  await Promise.all([queryClient.invalidateQueries({ queryKey: ['categories'] }), invalidateFinance()]);
}
