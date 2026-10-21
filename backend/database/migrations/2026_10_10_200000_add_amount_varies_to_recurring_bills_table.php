<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_bills', function (Blueprint $table) {
            // Contas como luz e água: o valor muda todo mês, o expected_cents vira só uma estimativa
            // e o valor exato é informado a cada pagamento.
            $table->boolean('amount_varies')->default(false)->after('expected_cents');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_bills', function (Blueprint $table) {
            $table->dropColumn('amount_varies');
        });
    }
};
