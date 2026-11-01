<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('text_en', 500);
            $table->string('text_el', 500);
            $table->json('answers_en');
            $table->json('answers_el');
            $table->unsignedTinyInteger('correct_index');
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'text_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
