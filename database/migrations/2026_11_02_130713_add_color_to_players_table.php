<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The palette in assignment order. Listed here rather than read from the enum so
     * the migration keeps working if the enum changes later.
     *
     * @var list<string>
     */
    private const array COLORS = [
        'violet', 'coral', 'mint', 'sky', 'rose', 'gold', 'teal', 'orchid', 'cyan', 'magenta',
        'lime', 'amber', 'tangerine', 'indigo', 'lavender', 'emerald', 'peach', 'ruby', 'pearl', 'steel',
    ];

    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->string('color', 20)->nullable()->after('name');
        });

        DB::table('players')
            ->orderBy('id')
            ->get(['id', 'room_id'])
            ->groupBy('room_id')
            ->each(function ($players): void {
                $players->values()->each(function (object $player, int $index): void {
                    DB::table('players')->where('id', $player->id)->update(['color' => self::COLORS[$index]]);
                });
            });

        Schema::table('players', function (Blueprint $table): void {
            $table->string('color', 20)->nullable(false)->change();
            $table->unique(['room_id', 'color']);
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropUnique(['room_id', 'color']);
            $table->dropColumn('color');
        });
    }
};
