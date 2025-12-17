<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            // Add unique constraint on eventbrite_ticket_id to ensure updateOrCreate works reliably
            $table->unique('eventbrite_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->dropUnique(['eventbrite_ticket_id']);
        });
    }
};
