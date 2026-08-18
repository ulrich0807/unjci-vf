<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // On supprime les anciennes colonnes seulement si elles existent
            if (Schema::hasColumn('members', 'cv_path')) {
                $table->dropColumn('cv_path');
            }
            if (Schema::hasColumn('members', 'professional_email')) {
                $table->dropColumn('professional_email');
            }
            if (Schema::hasColumn('members', 'personal_email')) {
                // Si tu avais personal_email, on peut le renommer ou s'assurer qu'un champ email unique existe
                // $table->renameColumn('personal_email', 'email');
            }

            // On ajoute les nouvelles colonnes pour la carte de presse recto-verso
            if (!Schema::hasColumn('members', 'press_card_recto')) {
                $table->string('press_card_recto')->nullable();
            }
            if (!Schema::hasColumn('members', 'press_card_verso')) {
                $table->string('press_card_verso')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['press_card_recto', 'press_card_verso']);
        });
    }
};