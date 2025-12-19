<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->string('pregame_interest')->nullable()->after('gender');
        });
    }

    public function down()
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->dropColumn('pregame_interest');
        });
    }
};
