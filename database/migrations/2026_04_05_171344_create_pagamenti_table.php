<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagamenti', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('cliente')
                ->cascadeOnDelete();

            $table->foreignId('abbonamento_id')
                ->nullable()
                ->constrained('abbonamenti')
                ->nullOnDelete();

            $table->string('tipo'); // pacchetto, rata, mensile
            $table->date('competenza_da')->nullable();
            $table->date('competenza_a')->nullable();
            $table->string('descrizione')->nullable();

            $table->decimal('importo_previsto', 10, 2);
            $table->decimal('importo_pagato', 10, 2)->default(0);

            $table->date('scadenza')->nullable();
            $table->date('data_saldo')->nullable();

            $table->string('stato')->default('da_pagare'); // da_pagare, parziale, pagato, annullato

            $table->unsignedInteger('numero_rata')->nullable();
            $table->unsignedInteger('totale_rate')->nullable();

            $table->string('google_calendar_event_id')->nullable();
            $table->string('calendar_sync_status')->nullable();
            $table->text('calendar_last_error')->nullable();

            $table->timestamps();

            $table->index(['cliente_id', 'stato']);
            $table->index(['abbonamento_id', 'tipo']);
            $table->index(['scadenza']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamenti');
    }
};