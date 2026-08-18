<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'members_current_member_number_unique';

    public function up(): void
    {
        if (Schema::hasIndex('members', ['current_member_number'], 'unique')) {
            return;
        }

        $hasDuplicates = DB::table('members')
            ->whereNotNull('current_member_number')
            ->groupBy('current_member_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Index unique non créé : des numéros UNJCI provisoires sont utilisés par plusieurs membres.'
            );
        }

        Schema::table('members', function (Blueprint $table) {
            $table->unique('current_member_number', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('members', self::INDEX_NAME)) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropUnique(self::INDEX_NAME);
            });
        }
    }
};
