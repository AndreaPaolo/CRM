<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->dropForeign(['abbonamento_id']);

            $table->foreign('abbonamento_id')
                ->references('id')
                ->on('abbonamenti')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->dropForeign(['abbonamento_id']);

            $table->foreign('abbonamento_id')
                ->references('id')
                ->on('abbonamenti');
        });
    }
};