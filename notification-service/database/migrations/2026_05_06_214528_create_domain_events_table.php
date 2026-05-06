<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->string('correlation_id', 128)->index();
            $table->string('type', 64)->index();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 32)->default('received');
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['correlation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};
