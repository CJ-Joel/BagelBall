<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->string('eventbrite_order_id')->nullable()->after('eventbrite_ticket_id');
            $table->string('first_name')->nullable()->after('eventbrite_order_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            
            // Add indexes for faster lookups
            $table->index('eventbrite_order_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->dropIndex(['eventbrite_order_id']);
            $table->dropIndex(['email']);
            $table->dropColumn(['eventbrite_order_id', 'first_name', 'last_name', 'email']);
        });
    }
};
