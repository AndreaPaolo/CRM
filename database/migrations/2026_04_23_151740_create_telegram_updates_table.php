<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_update_id')->nullable()->index();
            $table->string('direction', 20); // inbound|outbound
            $table->string('kind', 50)->nullable(); // message|callback|system
            $table->string('chat_id')->nullable()->index();
            $table->string('telegram_user_id')->nullable()->index();
            $table->string('telegram_message_id')->nullable();
            $table->text('text')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_updates');
    }
};