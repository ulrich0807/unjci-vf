<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['press_card_file_path', 'cv_file_path'],
            fn ($column) => Schema::hasColumn('members', $column),
        ));

        if ($columns) {
            Schema::table('members', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'press_card_file_path')) {
                $table->string('press_card_file_path')->nullable();
            }
            if (! Schema::hasColumn('members', 'cv_file_path')) {
                $table->string('cv_file_path')->nullable();
            }
        });
    }
};
