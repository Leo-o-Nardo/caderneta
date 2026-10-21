<?php

namespace App\Console\Commands;

use App\Assistant\Actions\ActionService;
use App\Models\PendingAction;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Faz pelo terminal o que os botões do card fazem no app, como a pessoa que pediu a ação. O id
 * aparece no card que o assistant:ask imprime ("action": {"id": …}).
 *
 *   php artisan assistant:action 01JA… confirm
 */
class AssistantAction extends Command
{
    protected $signature = 'assistant:action
        {id : O id da ação (o "id" dentro de "action" no card)}
        {operation : confirm, cancel ou undo}';

    protected $description = 'Confirma, cancela ou desfaz uma ação do assistente, como os botões do card';

    public function handle(ActionService $actions): int
    {
        $action = PendingAction::query()->with('user')->where('ulid', $this->argument('id'))->first();

        if ($action === null) {
            $this->error('Nenhuma ação com esse id.');

            return self::FAILURE;
        }

        try {
            $card = match ($this->argument('operation')) {
                'confirm' => $actions->confirm($action, $action->user, null),
                'cancel' => $actions->cancel($action, $action->user),
                'undo' => $actions->undo($action, $action->user),
                default => null,
            };
        } catch (ValidationException $error) {
            $this->error(implode(' ', array_merge(...array_values($error->errors()))));

            return self::FAILURE;
        } catch (HttpException $error) {
            $this->error("Recusado ({$error->getStatusCode()}).");

            return self::FAILURE;
        }

        if ($card === null) {
            $this->error('Use confirm, cancel ou undo.');

            return self::INVALID;
        }

        $this->line(json_encode($card, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
