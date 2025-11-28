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
        Schema::table('pregames', function (Blueprint $table) {
            $table->string('partiful_url')->nullable()->after('eventbrite_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pregames', function (Blueprint $table) {
            $table->dropColumn('partiful_url');
        });
    }
};
