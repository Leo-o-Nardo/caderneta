<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\HouseholdMemberController;
use App\Http\Controllers\InstallmentPlanController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ListItemController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PushTokenController;
use App\Http\Controllers\RecurringIncomeController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:auth')->prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [PasswordController::class, 'forgot']);
        Route::post('reset-password', [PasswordController::class, 'reset']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->middleware('household');
        Route::patch('me', [AuthController::class, 'updateMe'])->middleware('household');
        Route::put('me/password', [PasswordController::class, 'update']);
        Route::delete('me', [AuthController::class, 'destroyMe'])->middleware('throttle:auth');
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);

        // O celular que recebe os avisos como push. Sai sozinho junto com o login (ver PushTokenController).
        Route::post('push-tokens', [PushTokenController::class, 'store']);

        Route::get('households', [HouseholdController::class, 'index']);
        Route::post('households', [HouseholdController::class, 'store']);
        Route::put('households/current', [HouseholdController::class, 'switchCurrent']);

        Route::middleware('throttle:invitations')->group(function () {
            Route::get('invitations/{code}', [InvitationController::class, 'show']);
            Route::post('invitations/{code}/accept', [InvitationController::class, 'accept']);
        });

        // Daqui para baixo, tudo trabalha na casa atual do usuário (ver ResolveCurrentHousehold).
        Route::middleware('household')->prefix('household')->group(function () {
            Route::get('/', [HouseholdController::class, 'show']);
            Route::patch('/', [HouseholdController::class, 'update']);
            Route::delete('/', [HouseholdController::class, 'destroy']);
            Route::post('leave', [HouseholdController::class, 'leave']);

            Route::patch('members/{member}', [HouseholdMemberController::class, 'update'])->whereNumber('member');
            Route::delete('members/{member}', [HouseholdMemberController::class, 'destroy'])->whereNumber('member');

            Route::get('invitations', [InvitationController::class, 'index']);
            Route::post('invitations', [InvitationController::class, 'store']);
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation');
        });

        // A conversa com o assistente na casa atual. Só leitura também conversa (sem as ferramentas que gravam).
        Route::middleware(['household', 'throttle:assistant'])->prefix('conversation')->group(function () {
            Route::get('/', [ConversationController::class, 'show']);
            Route::post('messages', [ConversationController::class, 'store']);
            // Mensagem de voz: vira texto na fila e segue como se tivesse sido digitada.
            Route::post('voice', [ConversationController::class, 'voice']);
            // "Reportar resposta": vai para o administrador revisar (exigência do Google Play para IA).
            Route::post('messages/{message}/report', [ConversationController::class, 'report'])->whereNumber('message');
            // "Limpar conversa": arquiva a atual (fica guardada); a próxima mensagem começa outra.
            Route::delete('/', [ConversationController::class, 'clear']);
        });

        // Os primeiros passos na casa atual (boas-vindas, checklist e tour). O "Só leitura" também usa.
        Route::middleware('household')->prefix('onboarding')->group(function () {
            Route::get('/', [OnboardingController::class, 'show']);
            Route::patch('/', [OnboardingController::class, 'update']);
        });

        // Os avisos no Chat (fase 7): o número da aba Chat e o "visto", na casa atual.
        Route::middleware('household')->prefix('alerts')->group(function () {
            Route::get('unread', [AlertController::class, 'unread']);
            Route::post('seen', [AlertController::class, 'seen']);
        });

        // Os botões dos cards de aviso. Como as ações do assistente, rodam na casa do aviso.
        Route::post('alerts/{message}/{action}', [AlertController::class, 'act'])
            ->whereNumber('message')
            ->whereIn('action', ['done', 'snooze_hour', 'tomorrow', 'remind_tomorrow', 'paid', 'undo']);

        // Os botões dos cards do assistente. A ação roda na casa onde foi criada (o ActionService
        // preenche o contexto), então não depende da casa atual.
        Route::middleware('throttle:assistant')->prefix('actions/{action}')->group(function () {
            Route::post('confirm', [ActionController::class, 'confirm']);
            Route::post('cancel', [ActionController::class, 'cancel']);
            Route::post('undo', [ActionController::class, 'undo']);
        });

        // Dados financeiros da casa atual. Quem é só leitura pode ler, mas não gravar.
        Route::middleware(['household', 'household.write'])->group(function () {
            // Antes do apiResource: senão o "order" viraria o {category} do PUT categories/{category}.
            Route::put('categories/order', [CategoryController::class, 'reorder']);
            Route::apiResource('categories', CategoryController::class)->except('show');
            Route::apiResource('cards', CardController::class)->except('show');
            Route::get('cards/{card}/invoice', [InvoiceController::class, 'show']);

            Route::apiResource('transactions', TransactionController::class);
            Route::apiResource('installment-plans', InstallmentPlanController::class)->except('index');

            // As rotas fixas vêm antes das que têm {bill}, para "upcoming" e "month" não virarem um id.
            Route::get('bills/upcoming', [BillController::class, 'upcoming']);
            Route::get('bills/month', [BillController::class, 'month']);
            Route::apiResource('bills', BillController::class);
            Route::post('bills/{bill}/pay', [BillController::class, 'pay']);
            Route::post('bills/{bill}/skip', [BillController::class, 'skip']);
            Route::delete('bills/{bill}/payments/{month}', [BillController::class, 'undo'])->where('month', '\d{4}-\d{2}');

            // Receitas fixas: nascem aqui (Agenda) ou pelo "Repetir todo mês" do POST transactions.
            Route::apiResource('recurring-incomes', RecurringIncomeController::class);

            Route::get('budgets', [BudgetController::class, 'index']);
            Route::get('budgets/{category}', [BudgetController::class, 'show']);
            Route::put('budgets/{category}', [BudgetController::class, 'update']);
            Route::delete('budgets/{category}', [BudgetController::class, 'destroy']);

            Route::get('summary', [SummaryController::class, 'show']);

            Route::apiResource('lists', ListController::class)->except('show');
            Route::get('lists/{list}/items', [ListItemController::class, 'index']);
            Route::post('lists/{list}/items', [ListItemController::class, 'store']);
            Route::post('lists/{list}/check', [ListController::class, 'check']);
            Route::delete('lists/{list}/checked', [ListController::class, 'clearChecked']);
            Route::put('lists/{list}/order', [ListController::class, 'reorder']);
            Route::patch('list-items/{listItem}', [ListItemController::class, 'update']);
            Route::delete('list-items/{listItem}', [ListItemController::class, 'destroy']);

            Route::apiResource('reminders', ReminderController::class)->except('show');
            Route::post('reminders/{reminder}/done', [ReminderController::class, 'done']);
            Route::post('reminders/{reminder}/snooze', [ReminderController::class, 'snooze']);
        });
    });
});
