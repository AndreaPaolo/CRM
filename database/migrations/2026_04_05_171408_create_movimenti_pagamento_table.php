<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimenti_pagamento', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pagamento_id')
                ->constrained('pagamenti')
                ->cascadeOnDelete();

            $table->date('data_pagamento');
            $table->decimal('importo', 10, 2);
            $table->string('metodo')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimenti_pagamento');
    }
};