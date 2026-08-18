<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'member_number')) {
                $table->string('member_number')->nullable()->unique()->after('current_member_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'member_number')) {
                $table->dropUnique(['member_number']);
                $table->dropColumn('member_number');
            }
        });
    }
};
