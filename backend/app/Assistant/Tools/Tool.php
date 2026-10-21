<?php

namespace App\Assistant\Tools;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;

/**
 * A base das ferramentas: valida os argumentos antes de rodar. A IA às vezes manda uma data
 * malformada ou um campo a mais; em vez de quebrar, o erro volta para ela corrigir ou perguntar.
 */
abstract class Tool implements AssistantTool
{
    public function tier(): ToolTier
    {
        return ToolTier::Read;
    }

    /** As regras de validação dos mesmos campos de parameters(). */
    abstract protected function rules(): array;

    abstract protected function run(array $args, ToolContext $context): ToolResult;

    public function handle(array $args, ToolContext $context): ToolResult
    {
        $validator = Validator::make($args, $this->rules());

        if ($validator->fails()) {
            return ToolResult::error('Argumentos inválidos: '.implode(' ', $validator->errors()->all()));
        }

        return $this->run($validator->validated(), $context);
    }

    /** "2026-10-05" → "05/10": datas curtas, como as pessoas falam. */
    protected static function shortDate(CarbonInterface|string $date): string
    {
        return ($date instanceof CarbonInterface ? $date : CarbonImmutable::parse($date))->format('d/m');
    }
}
