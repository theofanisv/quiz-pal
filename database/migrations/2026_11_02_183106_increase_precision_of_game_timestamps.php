<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The round and answer models write their timestamps with milliseconds (Round::DATE_FORMAT),
 * but a MySQL TIMESTAMP without precision rounds the fraction to the nearest second. That put
 * stored answer times up to half a second off and made the timed waits (the get-ready countdown,
 * the next-button hold) half a second short or long. SQLite keeps the fraction either way.
 *
 * Rolling back rounds the stored values to whole seconds again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->timestamp('started_at', precision: 3)->nullable()->change();
            $table->timestamp('ends_at', precision: 3)->nullable()->change();
            $table->timestamp('revealed_at', precision: 3)->nullable()->change();
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->timestamp('answered_at', precision: 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->change();
            $table->timestamp('ends_at')->nullable()->change();
            $table->timestamp('revealed_at')->nullable()->change();
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->timestamp('answered_at')->change();
        });
    }
};
