<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // A ordem escolhida pela casa, contada dentro de cada tipo (despesas e receitas).
            $table->unsignedSmallInteger('position')->default(0)->after('color');
        });

        // As categorias que já existem ficam na ordem de hoje (a de cadastro), por casa e por tipo.
        DB::statement(<<<'SQL'
            UPDATE categories
            SET position = ranked.row_number - 1
            FROM (
                SELECT id, row_number() OVER (PARTITION BY household_id, kind ORDER BY id) AS row_number
                FROM categories
            ) AS ranked
            WHERE categories.id = ranked.id
        SQL);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
