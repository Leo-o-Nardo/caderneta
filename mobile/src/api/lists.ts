import { useMutation, useQuery } from '@tanstack/react-query';

import { api } from '@/lib/api';
import { queryClient } from '@/lib/queryClient';

import { keys } from './keys';
import type { HouseholdList, ListItem, ListKind, Visibility } from './types';

export type ListInput = {
  name: string;
  kind: ListKind;
  visibility: Visibility;
  is_default?: boolean;
};

export type ListItemInput = {
  text: string;
  quantity?: string | null;
  note?: string | null;
  price_cents?: number | null;
  url?: string | null;
  due_on?: string | null;
};

/** As listas que a pessoa vê (as privadas de outros membros não aparecem), com as contagens. */
export function useLists() {
  return useQuery({ queryKey: keys.lists, queryFn: () => api<HouseholdList[]>('/lists') });
}

/** Os itens de uma lista: em aberto primeiro, os já pegos no fim. */
export function useListItems(listId: number) {
  return useQuery({ queryKey: keys.listItems(listId), queryFn: () => api<ListItem[]>(`/lists/${listId}/items`) });
}

export function createList(input: ListInput): Promise<HouseholdList> {
  return api('/lists', { method: 'POST', body: input });
}

export function updateList(id: number, input: Partial<ListInput>): Promise<HouseholdList> {
  return api(`/lists/${id}`, { method: 'PATCH', body: input });
}

/** Apaga a lista com os itens. A lista padrão não pode ser apagada. */
export function deleteList(id: number): Promise<void> {
  return api(`/lists/${id}`, { method: 'DELETE' });
}

/**
 * Adiciona vários itens de uma vez. A API não repete o que já está em aberto ("existing") e
 * reabre o que já tinha sido pego.
 */
export function addListItems(listId: number, items: ListItemInput[]): Promise<{ added: ListItem[]; existing: ListItem[] }> {
  return api(`/lists/${listId}/items`, { method: 'POST', body: { items } });
}

export function updateListItem(id: number, input: Partial<ListItemInput> & { checked?: boolean }): Promise<ListItem> {
  return api(`/list-items/${id}`, { method: 'PATCH', body: input });
}

export function deleteListItem(id: number): Promise<void> {
  return api(`/list-items/${id}`, { method: 'DELETE' });
}

/** Apaga os itens já pegos. */
export function clearCheckedItems(listId: number): Promise<{ deleted: number }> {
  return api(`/lists/${listId}/checked`, { method: 'DELETE' });
}

/**
 * O toque que marca ou desmarca um item. A tela muda na hora, antes da resposta da API (no
 * mercado o sinal costuma ser ruim); se a API falhar, o item volta como estava.
 */
export function useToggleItem(listId: number) {
  const key = keys.listItems(listId);

  return useMutation({
    mutationFn: ({ id, checked }: { id: number; checked: boolean }) => updateListItem(id, { checked }),
    onMutate: async ({ id, checked }) => {
      await queryClient.cancelQueries({ queryKey: key });
      const previous = queryClient.getQueryData<ListItem[]>(key);

      queryClient.setQueryData<ListItem[]>(key, (items) =>
        items?.map((item) => (item.id === id ? { ...item, is_checked: checked } : item)),
      );

      return { previous };
    },
    onSuccess: (saved) => {
      // A resposta traz quem pegou e quando: substitui só este item, sem buscar a lista inteira de novo.
      queryClient.setQueryData<ListItem[]>(key, (items) => items?.map((item) => (item.id === saved.id ? saved : item)));
    },
    onError: (_error, _variables, context) => {
      if (context?.previous) {
        queryClient.setQueryData(key, context.previous);
      }
    },
    // Só as contagens das listas; os itens já estão certos na tela.
    onSettled: () => queryClient.invalidateQueries({ queryKey: keys.lists }),
  });
}

/** Depois de criar, editar ou apagar listas e itens. */
export function invalidateLists(): Promise<void> {
  return queryClient.invalidateQueries({ queryKey: ['lists'] });
}
