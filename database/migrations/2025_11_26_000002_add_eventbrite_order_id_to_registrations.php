<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('eventbrite_order_id')->nullable()->after('eventbrite_ticket_id');
            $table->index('eventbrite_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['eventbrite_order_id']);
            $table->dropColumn('eventbrite_order_id');
        });
    }
};
