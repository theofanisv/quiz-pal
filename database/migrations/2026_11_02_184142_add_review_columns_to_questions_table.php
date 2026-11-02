<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Players report a question they believe is wrong (`reported_at`); a later review confirms or
 * fixes it (`verified_at`). A question waits for review while it was reported after its last
 * verification.
 *
 * Rolling back drops both columns and with them every pending report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->timestamp('reported_at')->nullable()->index()->after('difficulty');
            $table->timestamp('verified_at')->nullable()->after('reported_at');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['reported_at']);
            $table->dropColumn(['reported_at', 'verified_at']);
        });
    }
};
