<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 60);
            $table->foreignId('category_id')->constrained();
            // Nulo quando o valor muda todo mês (luz, água).
            $table->bigInteger('expected_cents')->nullable();
            $table->unsignedTinyInteger('due_day');
            $table->boolean('autopay')->default(false);
            // Dia 1º do primeiro mês em que a conta existe.
            $table->date('starts_on');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        // Só os meses que foram pagos ou pulados. Os outros são calculados (ver BillService).
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurring_bill_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('status', 10);
            // Apagar a despesa do pagamento apaga esta linha, e o mês volta a ficar em aberto.
            $table->foreignId('transaction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['recurring_bill_id', 'month']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('limit_cents');
            $table->timestamps();

            $table->unique(['household_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('recurring_bills');
    }
};
