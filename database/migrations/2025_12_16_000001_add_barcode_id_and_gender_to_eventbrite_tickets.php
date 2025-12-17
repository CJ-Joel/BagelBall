<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBarcodeIdAndGenderToEventbriteTickets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->string('barcode_id')->nullable()->after('email');
            $table->string('gender', 50)->nullable()->after('barcode_id');
            $table->index('barcode_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('eventbrite_tickets', function (Blueprint $table) {
            $table->dropIndex(['barcode_id']);
            $table->dropColumn(['barcode_id', 'gender']);
        });
    }
}
