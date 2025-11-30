<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pending_eventbrite_orders', function (Blueprint $table) {
            $table->id();
            $table->string('eventbrite_order_id')->unique();
            $table->string('eventbrite_event_id');
            $table->string('api_url');
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(12); // 12 retries × 10 seconds = 2 minutes
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('retry_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_eventbrite_orders');
    }
};
