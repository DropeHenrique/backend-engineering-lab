<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('lab_event_type', 64)->nullable()->index();
            $table->string('correlation_id', 128)->nullable()->index();
            $table->unsignedSmallInteger('http_status');
            $table->string('status', 24)->default('accepted');
            $table->json('response_body')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
