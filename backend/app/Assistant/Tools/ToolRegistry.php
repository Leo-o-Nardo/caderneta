<?php

namespace App\Assistant\Tools;

use App\Enums\HouseholdRole;

/** As ferramentas do assistente, pelo nome. A lista fica em config/assistant.php. */
final class ToolRegistry
{
    /** @var array<string, AssistantTool> */
    private array $tools = [];

    /** @param  iterable<AssistantTool>  $tools */
    public function __construct(iterable $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function find(string $name): ?AssistantTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * As ferramentas que este papel pode usar. Quem é só leitura nem fica sabendo das que gravam:
     * a IA não oferece o que a pessoa não pode fazer.
     *
     * @return list<AssistantTool>
     */
    public function availableFor(HouseholdRole $role): array
    {
        return array_values(array_filter(
            $this->tools,
            fn (AssistantTool $tool) => $role->canWrite() || ! $tool->tier()->writes(),
        ));
    }
}
