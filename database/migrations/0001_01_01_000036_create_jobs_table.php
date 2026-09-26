<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The table SendQueuedWhatsApp / SendQueuedGuestMail were always meant to
| use
|--------------------------------------------------------------------------
| Those two jobs (app/Jobs/SendQueuedWhatsApp.php, SendQueuedGuestMail.php)
| already move the slow part of a guest message — the actual gateway/SMTP
| call — off the web request. But QUEUE_CONNECTION has been "sync" in
| .env, which makes every dispatch() run immediately, inline, in the same
| request, as if this table never existed — so the wait guests' WhatsApp
| and PDF sends put on every checkin/checkout/booking/POS action was still
| happening in full. This table plus QUEUE_CONNECTION=database is what
| switches that dispatch() from "run it right now" to "drop it here and
| let php artisan queue:work pick it up" — the request returns immediately
| either way.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        // Where a job lands after its retries (SendQueuedWhatsApp: 3,
        // SendQueuedGuestMail: 3) are all used up — so a gateway that is
        // down for good is a row here to look at, not a message that
        // silently vanished.
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('failed_jobs');
    }
};
