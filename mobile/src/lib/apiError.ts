/** Um erro de chamada à API, já com a mensagem pronta para mostrar ao usuário. */
export class ApiError extends Error {
  readonly status: number;
  readonly fieldErrors: Record<string, string[]>;

  constructor(status: number, message: string, fieldErrors: Record<string, string[]> = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.fieldErrors = fieldErrors;
  }

  /** A primeira mensagem de um campo, para mostrar embaixo dele. */
  field(name: string): string | undefined {
    return this.fieldErrors[name]?.[0];
  }

  get hasFieldErrors(): boolean {
    return Object.keys(this.fieldErrors).length > 0;
  }

  /**
   * A mensagem para mostrar fora dos campos: só quando nenhum dos campos que a tela exibe tem erro.
   * Assim um erro num campo que não aparece na tela (ou um erro geral) não fica escondido.
   */
  messageOutside(visibleFields: string[]): string | null {
    return visibleFields.some((name) => this.field(name)) ? null : this.message;
  }
}

export const OFFLINE_MESSAGE = 'Sem conexão com o servidor. Confira a internet e tente de novo.';
const SERVER_MESSAGE = 'Algo deu errado do nosso lado. Tente de novo em instantes.';

// As mensagens padrão do Laravel para esses casos vêm em inglês; estas são as do app.
const STATUS_MESSAGES: Record<number, string> = {
  401: 'Sua sessão expirou. Entre de novo.',
  403: 'Você não tem permissão para fazer isso.',
  404: 'Não encontramos o que você procurou.',
  429: 'Muitas tentativas seguidas. Espere um pouco e tente de novo.',
};

/** Converte a resposta de erro do Laravel ({ message, errors }) num ApiError. */
export function toApiError(status: number, body: unknown): ApiError {
  const data = (body !== null && typeof body === 'object' ? body : {}) as { message?: unknown; errors?: unknown };
  const fieldErrors = isFieldErrors(data.errors) ? data.errors : {};

  if (status === 422) {
    // A mensagem geral do Laravel é "erro do 1º campo (e mais N erros)"; a do 1º campo basta.
    const first = Object.values(fieldErrors)[0]?.[0];

    return new ApiError(status, first ?? 'Confira os dados informados.', fieldErrors);
  }

  if (status >= 500) {
    return new ApiError(status, SERVER_MESSAGE);
  }

  return new ApiError(status, STATUS_MESSAGES[status] ?? SERVER_MESSAGE, fieldErrors);
}

function isFieldErrors(value: unknown): value is Record<string, string[]> {
  return value !== null && typeof value === 'object' && Object.values(value).every(Array.isArray);
}
