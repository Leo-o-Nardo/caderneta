<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_user', function (Blueprint $table) {
            // Os primeiros passos de cada pessoa em cada casa (ver OnboardingService): só o que não
            // dá para tirar dos cadastros, como as boas-vindas vistas, os passos ignorados e o
            // ponto do tour. Vazio: nada visto ainda.
            $table->json('onboarding')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('household_user', fn (Blueprint $table) => $table->dropColumn('onboarding'));
    }
};
