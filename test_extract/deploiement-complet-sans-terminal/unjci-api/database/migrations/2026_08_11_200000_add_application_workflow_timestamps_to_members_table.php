<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('application_submitted_at')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('application_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'application_submitted_at',
                'approved_at',
            ]);
        });
    }
};
