/**
 * Dinheiro no app: a API trabalha sempre em centavos (inteiros). Aqui só formatamos.
 * A formatação é manual, e não pelo Intl, para sair igual no Android, no iPhone e nos testes.
 */

/** 4500 → "R$ 45,00"; 123456789 → "R$ 1.234.567,89". Com sign: true, positivos ganham "+". */
export function formatCents(cents: number, { sign = false }: { sign?: boolean } = {}): string {
  const absolute = Math.abs(Math.round(cents));
  const reais = Math.floor(absolute / 100)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const centavos = (absolute % 100).toString().padStart(2, '0');
  const prefix = cents < 0 ? '-' : sign && cents > 0 ? '+' : '';

  return `${prefix}R$ ${reais},${centavos}`;
}

/**
 * O texto de um valor pelas regras do guia: receita leva "+" ("+R$ 650,00"), valor estimado leva
 * "~" ("~R$ 189,00"), despesa vai sem sinal e negativo (saldo) mantém o "-".
 */
export function amountLabel(cents: number, { income = false, estimate = false }: { income?: boolean; estimate?: boolean } = {}): string {
  return `${estimate ? '~' : ''}${formatCents(cents, { sign: income })}`;
}

/**
 * Lê o que foi digitado num campo de valor como num app de banco: só os dígitos contam, e os dois
 * últimos são os centavos. "4500" → 4500 (R$ 45,00); "R$ 1.234,56" → 123456.
 */
export function centsFromDigits(text: string): number {
  const digits = text.replace(/\D/g, '');

  return digits === '' ? 0 : Number.parseInt(digits, 10);
}
