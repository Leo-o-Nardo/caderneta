<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 40);
            $table->string('kind', 10);
            $table->string('icon', 30);
            $table->unsignedInteger('position')->default(0);
            // A lista usada quando alguém diz só "coloca na lista". Uma por casa.
            $table->boolean('is_default')->default(false);
            $table->string('visibility', 10);
            $table->timestamps();
        });

        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('text', 120);
            $table->string('quantity', 20)->nullable();
            $table->string('note', 200)->nullable();
            $table->bigInteger('price_cents')->nullable();
            $table->string('url', 500)->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['list_id', 'checked_at']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('text', 160);
            // Próximo aviso, em UTC.
            $table->timestamp('remind_at');
            // A data original da série: as repetições são contadas a partir dela.
            $table->timestamp('anchor_at');
            $table->string('recurrence', 10);
            // "A cada N" dias, semanas, meses ou anos.
            $table->unsignedSmallInteger('every')->default(1);
            $table->string('visibility', 10);
            $table->timestamp('done_at')->nullable();
            // Usado pela rotina de avisos (fase 7), para não avisar duas vezes.
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('list_items');
        Schema::dropIfExists('lists');
    }
};
