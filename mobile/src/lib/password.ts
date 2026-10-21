export type PasswordCheck = { label: string; ok: boolean };

/**
 * A regra da senha do backend (Password::min(8)->letters()->numbers()), item por item, para a
 * pessoa ver o que falta antes de enviar: "8 caracteres", "letras", "números".
 */
export function passwordChecks(password: string): PasswordCheck[] {
  return [
    { label: '8 caracteres', ok: password.length >= 8 },
    // Letras com ou sem acento (as do português e do espanhol); o backend aceita qualquer letra.
    { label: 'letras', ok: /[A-Za-zÀ-ÖØ-öø-ÿ]/.test(password) },
    { label: 'números', ok: /\d/.test(password) },
  ];
}
