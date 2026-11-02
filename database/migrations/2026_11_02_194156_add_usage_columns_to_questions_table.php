<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How often each question was shown (`times_shown`) and when it was last shown (`last_shown_at`), so new games
 * can take the least-shown questions first. Rounds cannot keep this record: they go when a room is reset or pruned.
 *
 * Rolling back drops both columns and with them the whole usage history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedInteger('times_shown')->default(0)->after('verified_at');
            $table->timestamp('last_shown_at')->nullable()->after('times_shown');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['times_shown', 'last_shown_at']);
        });
    }
};
