<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('eventbrite_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregame_id')->constrained('pregames')->onDelete('cascade');
            $table->string('eventbrite_ticket_id');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventbrite_tickets');
    }
};
