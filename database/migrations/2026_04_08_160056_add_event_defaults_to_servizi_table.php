<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servizi', function (Blueprint $table) {
            $table->string('tipo_appuntamento_default')
                ->default('personal')
                ->after('tipo_fatturazione');

            $table->boolean('evento_intera_giornata_default')
                ->default(false)
                ->after('tipo_appuntamento_default');

            $table->boolean('crea_google_meet_default')
                ->default(false)
                ->after('evento_intera_giornata_default');

            $table->boolean('prenotazione_autonoma_cliente')
                ->default(false)
                ->after('crea_google_meet_default');
        });
    }

    public function down(): void
    {
        Schema::table('servizi', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_appuntamento_default',
                'evento_intera_giornata_default',
                'crea_google_meet_default',
                'prenotazione_autonoma_cliente',
            ]);
        });
    }
};