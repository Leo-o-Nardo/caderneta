<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_usage', function (Blueprint $table) {
            // Para que foi a chamada: "chat" (o assistente) ou "transcription" (mensagem de voz).
            $table->string('purpose', 20)->default('chat')->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('llm_usage', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
