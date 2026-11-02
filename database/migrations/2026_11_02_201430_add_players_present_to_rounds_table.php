<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many players were connected when the round closed (`players_present`), so the game history can tell how many
 * of them answered before the time ran out. Rounds revealed before this column stay null and carry no such signal.
 *
 * Rolling back drops the column and with it that part of the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedSmallInteger('players_present')->nullable()->after('revealed_at');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('players_present');
        });
    }
};
