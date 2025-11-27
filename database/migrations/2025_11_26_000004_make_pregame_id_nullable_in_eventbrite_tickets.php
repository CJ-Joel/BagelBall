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
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->dropForeign(['pregame_id']);
            $table->foreignId('pregame_id')->nullable()->change()->constrained('pregames')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->dropForeign(['pregame_id']);
            $table->foreignId('pregame_id')->nullable(false)->change()->constrained('pregames')->onDelete('cascade');
        });
    }
};
