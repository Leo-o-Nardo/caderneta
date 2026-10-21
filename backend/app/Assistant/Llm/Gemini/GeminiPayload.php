<?php

namespace App\Assistant\Llm\Gemini;

use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Tools\AssistantTool;
use stdClass;

/**
 * Traduz uma rodada do formato neutro do assistente para o corpo do generateContent: instruções,
 * conversa (user/model), pedidos de ferramenta com a assinatura de raciocínio, resultados e as
 * declarações das ferramentas.
 */
final class GeminiPayload
{
    // Ids que o Gemini não mandou (modelos antigos) e foram criados aqui: não voltam para ele.
    public const LOCAL_ID_PREFIX = 'local-';

    public static function build(LlmRequest $request, array $config): array
    {
        $body = [
            'systemInstruction' => ['parts' => [['text' => $request->system]]],
            'contents' => self::contents($request->messages),
            'generationConfig' => [
                // Inclui os tokens de raciocínio: com pouco, a resposta sai cortada (e é cobrada).
                'maxOutputTokens' => (int) $config['max_output_tokens'],
                'thinkingConfig' => ['thinkingLevel' => $config['thinking_level']],
            ],
        ];

        if ($request->tools !== []) {
            $body['tools'] = [['functionDeclarations' => array_map(self::declaration(...), $request->tools)]];
            $body['toolConfig'] = ['functionCallingConfig' => ['mode' => 'AUTO']];
        }

        return $body;
    }

    /**
     * A conversa no formato do Gemini. Mensagens seguidas do mesmo papel viram uma só (vários
     * resultados de ferramenta da mesma rodada vão juntos, na ordem em que foram pedidos).
     *
     * @param  list<LlmMessage>  $messages
     */
    private static function contents(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            [$role, $parts] = self::turn($message);

            if ($parts === []) {
                continue;
            }

            $last = array_key_last($contents);

            if ($last !== null && $contents[$last]['role'] === $role) {
                $contents[$last]['parts'] = [...$contents[$last]['parts'], ...$parts];
            } else {
                $contents[] = ['role' => $role, 'parts' => $parts];
            }
        }

        return $contents;
    }

    /** @return array{0: string, 1: list<array>} o papel e as partes de uma mensagem */
    private static function turn(LlmMessage $message): array
    {
        if ($message->role === 'tool') {
            $response = ['name' => $message->call->name, 'response' => self::object($message->result)];

            if (! str_starts_with($message->call->id, self::LOCAL_ID_PREFIX)) {
                $response = ['id' => $message->call->id, ...$response];
            }

            return ['user', [['functionResponse' => $response]]];
        }

        $parts = $message->text !== null && $message->text !== '' ? [['text' => $message->text]] : [];

        foreach ($message->toolCalls as $call) {
            $functionCall = ['name' => $call->name, 'args' => self::object($call->args)];

            if (! str_starts_with($call->id, self::LOCAL_ID_PREFIX)) {
                $functionCall = ['id' => $call->id, ...$functionCall];
            }

            // A assinatura volta na mesma parte, exatamente como veio: sem ela o Gemini 3 recusa.
            $parts[] = array_filter(
                ['functionCall' => $functionCall, 'thoughtSignature' => $call->signature],
                fn ($value) => $value !== null,
            );
        }

        return [$message->role === 'user' ? 'user' : 'model', $parts];
    }

    private static function declaration(AssistantTool $tool): array
    {
        return [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => self::schema($tool->parameters()),
        ];
    }

    /** O JSON Schema da ferramenta, com "properties" vazio como objeto ({}), não lista ([]). */
    private static function schema(array $schema): array
    {
        // Algumas ferramentas já declaram "(object) []"; essas passam como estão.
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema['properties'] = $schema['properties'] === []
                ? new stdClass
                : array_map(self::schema(...), $schema['properties']);
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::schema($schema['items']);
        }

        return $schema;
    }

    /**
     * O Gemini espera objetos JSON nos argumentos e nos resultados: [] vira {}, e uma lista vai
     * dentro de {"result": [...]}.
     */
    private static function object(array $value): array|stdClass
    {
        if ($value === []) {
            return new stdClass;
        }

        return array_is_list($value) ? ['result' => $value] : $value;
    }
}
