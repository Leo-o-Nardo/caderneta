<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Receitas que se repetem todo mês (salário). A rotina incomes:launch lança cada mês no dia.
        Schema::create('recurring_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            // Quem recebe: os lançamentos saem em nome dessa pessoa, no fuso dela.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 120);
            $table->foreignId('category_id')->constrained();
            $table->bigInteger('amount_cents');
            $table->unsignedTinyInteger('day_of_month');
            $table->string('visibility', 10);
            // Dia 1º do último mês já lançado. A rotina só lança os meses depois dele: nunca lança
            // duas vezes, e um lançamento apagado de propósito não volta.
            $table->date('launched_through');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            // De qual receita fixa veio o lançamento. Parar de repetir não apaga o que já foi lançado.
            $table->foreignId('recurring_income_id')->nullable()->after('installment_number')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_income_id');
        });

        Schema::dropIfExists('recurring_incomes');
    }
};
