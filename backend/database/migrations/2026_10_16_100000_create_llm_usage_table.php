<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma linha por rodada de IA (uma pergunta pode ter 2 ou 3): tokens, tempo e custo estimado.
        Schema::create('llm_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20);
            $table->string('model', 60);
            $table->unsignedTinyInteger('round');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('thinking_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('latency_ms');
            // Em milionésimos de dólar (US$ 0,000221 = 221), pela tabela de preços de config/assistant.php.
            $table->unsignedBigInteger('cost_micros')->default(0);
            // Null quando deu certo; senão, o motivo (busy, blocked, failed).
            $table->string('error', 20)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_usage');
    }
};
