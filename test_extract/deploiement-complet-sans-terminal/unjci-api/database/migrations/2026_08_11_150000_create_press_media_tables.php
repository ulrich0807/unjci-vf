<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL ne garantit pas le rollback des instructions DDL. Le fichier doit
        // donc être complètement lisible et valide avant de créer la moindre table.
        $catalog = $this->loadInitialCatalog();

        Schema::create('press_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('press_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_company_id')
                ->constrained('press_companies')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['press_company_id', 'name']);
        });

        $this->importInitialCatalog($catalog);
    }

    public function down(): void
    {
        Schema::dropIfExists('press_media');
        Schema::dropIfExists('press_companies');
    }

    private function loadInitialCatalog(): array
    {
        $path = database_path('data/press-media.initial.json');

        if (! is_file($path)) {
            throw new RuntimeException("Fichier d'import des médias introuvable : {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Impossible de lire le fichier d'import des médias : {$path}");
        }

        $catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($catalog) || $catalog === []) {
            throw new RuntimeException("Le fichier d'import des médias est vide ou invalide : {$path}");
        }

        $knownMedia = [];
        foreach ($catalog as $index => $item) {
            if (! is_array($item)
                || ! isset($item['company'], $item['name'], $item['type'])
                || ! is_string($item['company'])
                || ! is_string($item['name'])
                || ! is_string($item['type'])
                || trim($item['company']) === ''
                || trim($item['name']) === ''
                || ! in_array($item['type'], ['Écrit', 'Numérique'], true)) {
                throw new RuntimeException("Entrée invalide à l'index {$index} du fichier d'import des médias.");
            }

            $key = json_encode([$item['company'], $item['name']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (isset($knownMedia[$key])) {
                throw new RuntimeException("Média dupliqué dans le fichier d'import : {$item['company']} / {$item['name']}.");
            }
            $knownMedia[$key] = true;
        }

        return $catalog;
    }

    private function importInitialCatalog(array $catalog): void
    {
        $now = now();
        $companyNames = array_values(array_unique(array_column($catalog, 'company')));

        DB::table('press_companies')->insert(array_map(
            fn (string $name) => [
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $companyNames,
        ));

        $companyIds = DB::table('press_companies')->pluck('id', 'name');
        $media = array_map(
            fn (array $item) => [
                'press_company_id' => $companyIds[$item['company']],
                'name' => $item['name'],
                'type' => $item['type'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $catalog,
        );

        foreach (array_chunk($media, 100) as $chunk) {
            DB::table('press_media')->insert($chunk);
        }
    }
};
