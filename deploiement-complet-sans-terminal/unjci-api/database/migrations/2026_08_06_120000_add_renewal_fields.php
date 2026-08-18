<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type', 20)->default('adhesion')->after('transaction_id');
            $table->string('previous_member_number')->nullable()->after('payment_type');
            $table->string('old_member_card_path')->nullable()->after('previous_member_number');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->date('membership_expires_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'payment_type',
                'previous_member_number',
                'old_member_card_path',
            ]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('membership_expires_at');
        });
    }
};
