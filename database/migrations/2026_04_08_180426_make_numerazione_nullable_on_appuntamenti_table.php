<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->unsignedInteger('numerazione')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->unsignedInteger('numerazione')->default(0)->nullable(false)->change();
        });
    }
};