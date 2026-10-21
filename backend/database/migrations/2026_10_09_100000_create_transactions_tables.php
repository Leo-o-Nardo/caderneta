<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('description', 120);
            $table->bigInteger('total_cents');
            $table->unsignedSmallInteger('installments');
            $table->date('purchased_on');
            $table->string('visibility', 10);
            $table->string('source', 10);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 10);
            $table->bigInteger('amount_cents');
            $table->string('description', 120);
            // A data que conta nos relatórios. Nas parcelas, anda um mês por parcela.
            $table->date('occurred_on');
            $table->foreignId('category_id')->constrained();
            $table->string('payment_type', 15)->nullable();
            $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            // Primeiro dia do mês em que fecha a fatura (só para compras no cartão).
            $table->date('invoice_month')->nullable();
            $table->foreignId('installment_plan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->string('visibility', 10);
            $table->string('source', 10);
            $table->timestamps();

            $table->index(['household_id', 'occurred_on']);
            $table->index(['household_id', 'card_id', 'invoice_month']);
            $table->index(['household_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('installment_plans');
    }
};
