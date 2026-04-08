<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->string('tipo_appuntamento')
                ->default('personal')
                ->after('numerazione');

            $table->boolean('evento_intera_giornata')
                ->default(false)
                ->after('tipo_appuntamento');

            $table->string('google_meet_link')
                ->nullable()
                ->after('google_calendar_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('appuntamenti', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_appuntamento',
                'evento_intera_giornata',
                'google_meet_link',
            ]);
        });
    }
};