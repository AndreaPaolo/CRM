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
        Schema::create('appuntamenti', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('cliente')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('abbonamento_id')
                ->constrained('abbonamenti')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->dateTime('data_ora');
            $table->unsignedInteger('durata')->default(60); // minuti
            $table->text('descrizione')->nullable();
            $table->unsignedInteger('numerazione');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appuntamenti');
    }
};
