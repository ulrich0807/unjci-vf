<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'media_name')) {
                $table->string('media_name')->nullable()->after('employers');
            }
            if (! Schema::hasColumn('members', 'media_type')) {
                $table->string('media_type', 20)->nullable()->after('media_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $columns = array_filter(['media_name', 'media_type'], fn ($column) => Schema::hasColumn('members', $column));
            if ($columns) $table->dropColumn($columns);
        });
    }
};
