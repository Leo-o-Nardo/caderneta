/** Monta a query string só com os filtros preenchidos: { month: "2026-10", q: "" } → "month=2026-10". */
export function toQuery(params: Record<string, string | number | null | undefined>): string {
  const search = new URLSearchParams();

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      search.append(key, String(value));
    }
  }

  return search.toString();
}
