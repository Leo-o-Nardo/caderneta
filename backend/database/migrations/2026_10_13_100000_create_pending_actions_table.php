<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O que o assistente propôs fazer: espera a confirmação (lançar, pagar, apagar) ou já foi
        // feito e ainda pode ser desfeito (itens de lista, lembretes).
        Schema::create('pending_actions', function (Blueprint $table) {
            $table->id();
            // O id que vai na URL: não dá para adivinhar o de outra pessoa somando 1.
            $table->ulid('ulid')->unique();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A resposta do assistente que mostra o card desta ação.
            $table->foreignId('message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('tool', 50);
            $table->string('tier', 20);
            $table->string('status', 20);
            // Os dados prontos para gravar, já validados com as regras do app.
            $table->jsonb('payload');
            // O que foi gravado (ids) e o necessário para desfazer.
            $table->jsonb('result')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('undo_until')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_actions');
    }
};
