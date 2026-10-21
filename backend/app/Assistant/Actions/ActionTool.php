<?php

namespace App\Assistant\Actions;

use App\Assistant\Tools\AssistantTool;
use App\Assistant\Tools\ToolContext;
use App\Assistant\Tools\ToolResult;
use Illuminate\Validation\ValidationException;

/**
 * Uma ferramenta que grava. A IA só chega até o prepare: monta e confere os dados, sem gravar.
 * O execute roda depois, quando a pessoa confirma no card (ou na hora, nas imediatas).
 */
interface ActionTool extends AssistantTool
{
    /**
     * Completa os argumentos da IA (acha a categoria pelo nome, o cartão padrão, a data de hoje)
     * sem gravar nada. Um problema volta para a IA como ToolResult de erro.
     */
    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult;

    /**
     * Valida o payload com as mesmas regras do app. Roda ao propor e de novo ao confirmar, com o
     * que a pessoa editou no card.
     *
     * @throws ValidationException
     */
    public function validatePayload(array $payload, ToolContext $context): array;

    /** O conteúdo do card (sem o pedaço "action"), a partir do payload e, depois de gravar, do resultado. */
    public function card(array $payload, ?array $result, ToolContext $context): array;

    /** Grava. Devolve o que foi feito: ids e o necessário para desfazer. */
    public function execute(array $payload, ToolContext $context): array;

    /** Só as imediatas: desfaz usando o que o execute devolveu. */
    public function undo(array $result, ToolContext $context): void;
}
