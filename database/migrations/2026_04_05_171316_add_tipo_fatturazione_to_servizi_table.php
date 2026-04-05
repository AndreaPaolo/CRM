<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servizi', function (Blueprint $table) {
            $table->string('tipo_fatturazione')->default('pacchetto');
        });
    }

    public function down(): void
    {
        Schema::table('servizi', function (Blueprint $table) {
  
        $table->dropColumn('tipo_fatturazione');
        });
    }
};