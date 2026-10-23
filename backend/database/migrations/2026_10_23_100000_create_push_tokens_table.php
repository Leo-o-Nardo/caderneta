<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Os celulares que recebem os avisos como push (ver SendPush). Um por aparelho.
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // O login (token do Sanctum) de onde o aparelho se registrou. Sair da conta apaga o
            // login e, junto, o aparelho: ninguém recebe aviso num celular de onde já saiu.
            $table->foreignId('personal_access_token_id')->constrained()->cascadeOnDelete();
            // O endereço do aparelho no serviço de push da Expo ("ExponentPushToken[...]").
            $table->string('token')->unique();
            // android ou ios.
            $table->string('platform', 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
    }
};
