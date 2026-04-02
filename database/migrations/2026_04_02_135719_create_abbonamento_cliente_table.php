<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abbonamento_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abbonamento_id')->constrained('abbonamenti')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('cliente')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['abbonamento_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abbonamento_cliente');
    }
};