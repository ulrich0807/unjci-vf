<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // ÉTAPE 1 : Identité
            $table->string('last_name');
            $table->string('first_name');
            $table->date('birth_date');
            $table->string('birth_place');
            $table->string('postal_address');
            $table->string('phone');
            $table->string('personal_email')->unique();

            // ÉTAPE 2 : Profession
            $table->string('request_type');
            $table->string('current_member_number')->nullable();
            $table->string('professional_status');
            $table->string('employers');
            $table->string('function_title');
            $table->string('press_card_number');
            $table->date('press_card_expiry');

            // ÉTAPE 3 : Justificatifs (Chemins des fichiers)
            $table->string('press_card_file_path');
            $table->string('cv_file_path')->nullable();
            $table->string('photo_file_path');

           

            // ÉTAPE 5 : Engagement
            $table->string('signature_name');
            $table->date('signature_date');
            $table->boolean('declaration_accepted')->default(true);
            $table->boolean('privacy_accepted')->default(true);

            // Statut interne de l'association
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
