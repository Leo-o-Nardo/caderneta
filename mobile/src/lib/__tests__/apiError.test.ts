import { ApiError, toApiError } from '../apiError';

describe('toApiError', () => {
  it('uses the first field error as the message of a validation error', () => {
    const error = toApiError(422, {
      message: 'Este e-mail já está em uso. (e mais 1 erro)',
      errors: {
        email: ['Este e-mail já está em uso.'],
        password: ['O campo senha deve ter pelo menos 8 caracteres.'],
      },
    });

    expect(error).toBeInstanceOf(ApiError);
    expect(error.message).toBe('Este e-mail já está em uso.');
    expect(error.field('password')).toBe('O campo senha deve ter pelo menos 8 caracteres.');
    expect(error.field('name')).toBeUndefined();
  });

  it('shows the general message only when no visible field has an error', () => {
    const error = toApiError(422, { errors: { device_name: ['O campo nome do aparelho é obrigatório.'] } });

    expect(error.messageOutside(['email', 'password'])).toBe('O campo nome do aparelho é obrigatório.');
    expect(error.messageOutside(['device_name'])).toBeNull();
  });

  it('uses the app messages for the other statuses', () => {
    expect(toApiError(403, { message: 'This action is unauthorized.' }).message).toBe('Você não tem permissão para fazer isso.');
    expect(toApiError(404, null).message).toBe('Não encontramos o que você procurou.');
    expect(toApiError(500, 'html de erro').message).toBe('Algo deu errado do nosso lado. Tente de novo em instantes.');
  });
});
