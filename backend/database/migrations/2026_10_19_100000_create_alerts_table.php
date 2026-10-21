<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada aviso mandado a uma pessoa (ver AlertService). A chave ("bill:12:2026-10:eve")
        // impede que o mesmo aviso saia duas vezes, mesmo se uma rotina rodar de novo.
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            // reminder, bill_due, autopay, budget ou weekly.
            $table->string('kind', 30);
            $table->string('key', 120);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'key']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            // Até quando a pessoa já viu os avisos: o número vermelho da aba Chat conta os depois disso.
            $table->timestamp('alerts_seen_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            // Os tipos de aviso que a pessoa desligou ({"bill_due": false}). Vazio: todos ligados.
            $table->json('alert_settings')->nullable();
        });

        Schema::table('recurring_bills', function (Blueprint $table) {
            // Dia 1º do último mês já tratado pelo débito automático (lançado, ou avisado quando o
            // valor varia). Um lançamento desfeito de propósito não volta.
            $table->date('autopay_through')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_bills', fn (Blueprint $table) => $table->dropColumn('autopay_through'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('alert_settings'));
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('alerts_seen_at'));
        Schema::dropIfExists('alerts');
    }
};
