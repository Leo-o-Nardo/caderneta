<?php

namespace App\Assistant\Actions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Valida dados do assistente com as regras de um FormRequest do app. Assim não existe um caminho
 * "mais frouxo" para a IA gravar: um lançamento do chat passa pelas mesmas regras do formulário.
 */
final class RequestRules
{
    /**
     * @param  class-string<FormRequest>  $request
     * @param  string  $prefix  na frente dos campos com erro ("items.1."), para o app achar o item certo
     * @param  array<string, mixed>  $route  os registros que a rota daria ao request, para as regras
     *                                       que leem a rota (a edição de lançamento olha o tipo do lançamento)
     *
     * @throws ValidationException
     */
    public static function validate(string $request, array $data, string $prefix = '', array $route = []): array
    {
        // Algumas regras leem outros campos da requisição (a categoria depende do "kind").
        $form = (new $request)->replace($data);

        if ($route !== []) {
            $form->setRouteResolver(function () use ($route) {
                $bound = new Route('PATCH', '', ['uses' => static fn () => null]);
                $bound->parameters = $route;

                return $bound;
            });
        }

        $validator = Validator::make($data, $form->rules(), $form->messages(), $form->attributes());

        if ($validator->fails()) {
            $errors = [];

            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[$prefix.$field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        return $validator->validated();
    }
}
