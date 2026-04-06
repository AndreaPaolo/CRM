<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appuntamento_cliente', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appuntamento_id')
                ->constrained('appuntamenti')
                ->cascadeOnDelete();

            $table->foreignId('cliente_id')
                ->constrained('cliente')
                ->cascadeOnDelete();

            $table->string('google_calendar_event_id')->nullable();
            $table->string('calendar_sync_status')->nullable();
            $table->timestamp('calendar_synced_at')->nullable();
            $table->text('calendar_last_error')->nullable();

            $table->timestamps();

            $table->unique(['appuntamento_id', 'cliente_id'], 'appuntamento_cliente_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appuntamento_cliente');
    }
};