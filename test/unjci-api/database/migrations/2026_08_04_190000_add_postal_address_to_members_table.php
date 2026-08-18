<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('members', 'postal_address')) {
            Schema::table('members', function (Blueprint $table) {
                $table->string('postal_address')->nullable()->after('birth_place');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('members', 'postal_address')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('postal_address');
            });
        }
    }
};
