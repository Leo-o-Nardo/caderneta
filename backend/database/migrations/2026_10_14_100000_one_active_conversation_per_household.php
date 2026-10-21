<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Limpar conversa" arquiva a conversa atual (ela fica guardada) e a próxima mensagem começa
 * outra. O banco garante que cada pessoa tenha uma só conversa ativa em cada casa, mesmo com duas
 * mensagens chegando ao mesmo tempo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Índice parcial (só do PostgreSQL): as arquivadas não contam.
        DB::statement('CREATE UNIQUE INDEX conversations_one_active ON conversations (user_id, household_id) WHERE archived_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS conversations_one_active');
    }
};
