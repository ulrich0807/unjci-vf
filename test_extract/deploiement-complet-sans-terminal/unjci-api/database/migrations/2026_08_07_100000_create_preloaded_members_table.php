<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preloaded_members', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('suggested_last_name')->nullable();
            $table->string('suggested_first_names')->nullable();
            $table->string('media_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('media_type', 20)->nullable();
            $table->string('press_card_number')->nullable()->unique();
            $table->string('member_number')->unique();
            $table->string('mapping_status', 20)->default('unmatched');
            $table->foreignId('member_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preloaded_members');
    }
};
