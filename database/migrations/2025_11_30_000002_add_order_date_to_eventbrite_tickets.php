<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->timestamp('order_date')->nullable()->after('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->dropColumn('order_date');
        });
    }
};
