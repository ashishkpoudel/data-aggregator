<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeEventOccurrencesIdToBigInt extends Migration
{
    public function up()
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->bigInteger('id')->change();
        });
    }

    public function down()
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->integer('id')->unsigned()->autoIncrement()->change();
        });
    }
}
