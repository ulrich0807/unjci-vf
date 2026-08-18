<?php

declare(strict_types=1);

/**
 * Génère l'import MySQL/phpMyAdmin du catalogue de presse à partir de la
 * même source JSON que la migration Laravel.
 */

function sqlString(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

$root = dirname(__DIR__);
$input = $argv[1] ?? $root . '/unjci-api/database/data/press-media.initial.json';
$output = $argv[2] ?? $root . '/unjci-api/database/press-media-import.sql';

$contents = file_get_contents($input);
if ($contents === false) {
    throw new RuntimeException("Impossible de lire {$input}.");
}

$catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
if (! is_array($catalog) || $catalog === []) {
    throw new RuntimeException('Le catalogue est vide ou invalide.');
}

$companies = [];
$knownMedia = [];
foreach ($catalog as $index => $item) {
    if (! is_array($item)
        || ! isset($item['company'], $item['name'], $item['type'])
        || ! is_string($item['company'])
        || ! is_string($item['name'])
        || ! in_array($item['type'], ['Écrit', 'Numérique'], true)) {
        throw new RuntimeException("Entrée invalide à l'index {$index}.");
    }

    $company = trim($item['company']);
    $name = trim($item['name']);
    if ($company === '' || $name === '') {
        throw new RuntimeException("Entreprise ou média vide à l'index {$index}.");
    }

    $key = $company . "\0" . $name;
    if (isset($knownMedia[$key])) {
        throw new RuntimeException("Média dupliqué : {$company} / {$name}.");
    }

    $knownMedia[$key] = true;
    $companies[$company] = true;
}

$companyNames = array_keys($companies);
sort($companyNames, SORT_STRING);

$companyRows = array_map(
    fn (string $name): string => '  (' . sqlString($name) . ', 1, NOW(), NOW())',
    $companyNames,
);

$mediaRows = [];
foreach ($catalog as $index => $item) {
    $select = $index === 0 ? '  SELECT ' : '  UNION ALL SELECT ';
    $mediaRows[] = $select
        . sqlString(trim($item['company'])) . ' AS company_name, '
        . sqlString(trim($item['name'])) . ' AS media_name, '
        . sqlString($item['type']) . ' AS media_type';
}

$sql = <<<'SQL'
-- Catalogue initial des entreprises de presse et médias UNJCI.
-- Importer ce fichier une seule fois depuis phpMyAdmin.
-- Généré automatiquement depuis database/data/press-media.initial.json.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `press_companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `press_companies_name_unique` (`name`),
  KEY `press_companies_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `press_media` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `press_company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `press_media_press_company_id_name_unique` (`press_company_id`, `name`),
  KEY `press_media_is_active_index` (`is_active`),
  KEY `press_media_press_company_id_foreign` (`press_company_id`),
  CONSTRAINT `press_media_press_company_id_foreign`
    FOREIGN KEY (`press_company_id`) REFERENCES `press_companies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT IGNORE INTO `press_companies`
  (`name`, `is_active`, `created_at`, `updated_at`)
VALUES
SQL;

$sql .= "\n" . implode(",\n", $companyRows) . ";\n\n";
$sql .= <<<'SQL'
INSERT IGNORE INTO `press_media`
  (`press_company_id`, `name`, `type`, `is_active`, `created_at`, `updated_at`)
SELECT
  company.`id`,
  catalog.`media_name`,
  catalog.`media_type`,
  1,
  NOW(),
  NOW()
FROM (
SQL;

$sql .= "\n" . implode("\n", $mediaRows) . "\n";
$sql .= <<<'SQL'
) AS catalog
INNER JOIN `press_companies` AS company
  ON company.`name` = catalog.`company_name`;

-- Empêche Laravel de tenter de recréer ces tables lors d'un futur accès au terminal.
SET @unjci_press_media_migration = '2026_08_11_150000_create_press_media_tables';
SET @unjci_press_media_batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`migration`, `batch`)
SELECT @unjci_press_media_migration, @unjci_press_media_batch
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE BINARY `migration` = BINARY @unjci_press_media_migration
);

COMMIT;

SELECT COUNT(*) AS `entreprises_chargees` FROM `press_companies`;
SELECT COUNT(*) AS `medias_charges` FROM `press_media`;
SQL;

$sql .= "\n";

if (file_put_contents($output, $sql) === false) {
    throw new RuntimeException("Impossible d'écrire {$output}.");
}

printf(
    "SQL généré : %s (%d entreprises, %d médias)\n",
    $output,
    count($companyNames),
    count($catalog),
);
