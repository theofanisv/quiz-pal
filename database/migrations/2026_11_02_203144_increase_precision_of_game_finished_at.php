<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The game model writes its timestamps with milliseconds (Round::DATE_FORMAT), but a MySQL TIMESTAMP without
 * precision rounds the fraction to the nearest second. `finished_at` is when the admin left the last answer, and the
 * game history reads the last round's pause from it, so it keeps the milliseconds like the round timestamps do.
 * Games finished before this keep their whole seconds.
 *
 * SQLite is left alone: it stores the timestamp as text and keeps the fraction anyway, and changing a column there
 * rebuilds `games`, whose old table cascades into `rounds` and deletes the whole history when it is dropped.
 *
 * Rolling back rounds the stored values to whole seconds again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('finished_at', precision: 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable()->change();
        });
    }
};
