<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            // Not a foreign key: players reference rooms, so the admin link is set after the admin joins.
            $table->unsignedBigInteger('admin_player_id')->nullable();
            $table->string('status')->default('lobby');
            $table->unsignedSmallInteger('total_rounds')->default(10);
            $table->unsignedSmallInteger('seconds_per_question')->default(20);
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
