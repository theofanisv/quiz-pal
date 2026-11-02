<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rolling back discards the Open Trivia DB mirror: its categories are deleted together
 * with their questions and room selections, because without the source tag the old code
 * would treat them as built-in questions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('source', 16)->default('custom')->index()->after('slug');
            $table->unsignedInteger('external_id')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        DB::table('categories')->where('source', 'opentdb')->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'external_id']);
        });
    }
};
