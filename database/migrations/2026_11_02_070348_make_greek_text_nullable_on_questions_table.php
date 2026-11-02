<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Questions synced from Open Trivia DB only exist in English; the Greek columns
 * stay empty and the models fall back to the English text.
 *
 * Rolling back discards the Open Trivia DB mirror: its English-only questions are
 * deleted first, because the columns become NOT NULL again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('text_el', 500)->nullable()->change();
            $table->json('answers_el')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('questions')
            ->whereIn('category_id', DB::table('categories')->where('source', 'opentdb')->select('id'))
            ->delete();

        Schema::table('questions', function (Blueprint $table) {
            $table->string('text_el', 500)->nullable(false)->change();
            $table->json('answers_el')->nullable(false)->change();
        });
    }
};
