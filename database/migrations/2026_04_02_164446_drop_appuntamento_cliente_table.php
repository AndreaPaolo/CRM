<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('appuntamento_cliente');
    }

    public function down(): void
    {
        Schema::create('appuntamento_cliente', function ($table) {
            //
        });
    }
};