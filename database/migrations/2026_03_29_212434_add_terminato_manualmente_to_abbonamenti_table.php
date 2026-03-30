<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abbonamenti', function (Blueprint $table) {
            $table->boolean('terminato_manualmente')->default(false)->after('terminato');
        });
    }

    public function down(): void
    {
        Schema::table('abbonamenti', function (Blueprint $table) {
            $table->dropColumn('terminato_manualmente');
        });
    }
};