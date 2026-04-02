<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->uuid('sessione_condivisa_uuid')->nullable()->after('abbonamento_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->dropColumn('sessione_condivisa_uuid');
        });
    }
};