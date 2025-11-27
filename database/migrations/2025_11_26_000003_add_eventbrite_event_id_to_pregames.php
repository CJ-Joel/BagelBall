<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pregames', function (Blueprint $table) {
            $table->string('eventbrite_event_id')->nullable()->after('id');
            $table->index('eventbrite_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('pregames', function (Blueprint $table) {
            $table->dropIndex(['eventbrite_event_id']);
            $table->dropColumn('eventbrite_event_id');
        });
    }
};
