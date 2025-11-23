<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregame_id')->constrained('pregames')->onDelete('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('friend_name')->nullable();
            $table->string('friend_email')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'redeemed'])->default('pending');
            $table->unsignedBigInteger('eventbrite_ticket_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
