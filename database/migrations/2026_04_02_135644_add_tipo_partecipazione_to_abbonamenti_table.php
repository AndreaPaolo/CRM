<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abbonamenti', function (Blueprint $table) {
            $table->string('tipo_partecipazione')
                ->default('singolo')
                ->after('servizio_id');
        });
    }

    public function down(): void
    {
        Schema::table('abbonamenti', function (Blueprint $table) {
            $table->dropColumn('tipo_partecipazione');
        });
    }
};