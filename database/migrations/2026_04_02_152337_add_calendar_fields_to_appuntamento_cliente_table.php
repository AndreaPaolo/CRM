<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appuntamento_cliente', function (Blueprint $table) {
            $table->string('google_calendar_event_id')->nullable()->after('cliente_id');
            $table->string('calendar_sync_status')->default('dirty')->after('google_calendar_event_id');
            $table->timestamp('calendar_synced_at')->nullable()->after('calendar_sync_status');
            $table->text('calendar_last_error')->nullable()->after('calendar_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('appuntamento_cliente', function (Blueprint $table) {
            $table->dropColumn([
                'google_calendar_event_id',
                'calendar_sync_status',
                'calendar_synced_at',
                'calendar_last_error',
            ]);
        });
    }
};