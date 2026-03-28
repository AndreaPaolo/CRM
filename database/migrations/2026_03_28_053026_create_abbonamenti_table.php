<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('abbonamenti', function (Blueprint $table) {
            $table->id();

            $table->foreignId('servizio_id')
                ->constrained('servizi')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('cliente_id')
                ->constrained('cliente')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('prezzo', 10, 2);
            $table->unsignedInteger('rate')->default(1);

            $table->date('data_inizio');
            $table->date('data_fine');

            $table->boolean('terminato')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abbonamenti');
    }
};
