<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// As receitas fixas (salário) entram sozinhas no dia. De hora em hora, para o dia virar no fuso de
// cada pessoa; quem roda é o contêiner "scheduler" (php artisan schedule:work).
Schedule::command('incomes:launch')->hourly()->withoutOverlapping();

// Os avisos no Chat (fase 7). Lembretes na hora certa; contas e débito automático de hora em hora,
// valendo a partir das 9h (ou no dia do vencimento) no fuso de cada pessoa; o resumo da semana no
// domingo, a partir das 19h. O aviso de orçamento não está aqui: sai pela fila, depois de cada gasto.
Schedule::command('alerts:reminders')->everyMinute()->withoutOverlapping();
Schedule::command('alerts:bills')->hourly()->withoutOverlapping();
Schedule::command('bills:autopay')->hourly()->withoutOverlapping();
Schedule::command('alerts:weekly')->hourly()->withoutOverlapping();

// Áudios de voz esquecidos (uma transcrição que travou no meio): nenhum fica mais de uma hora no
// servidor. No caminho normal, o áudio já é apagado logo depois de virar texto.
Schedule::call(function () {
    $disk = Storage::disk('local');
    $limit = now()->subHour()->getTimestamp();

    foreach ($disk->files('voice') as $file) {
        if ($disk->lastModified($file) < $limit) {
            $disk->delete($file);
        }
    }
})->hourly()->name('voice:prune')->withoutOverlapping();
