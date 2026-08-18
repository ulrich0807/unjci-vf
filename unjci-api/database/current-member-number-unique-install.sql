-- Sécurise l'attribution provisoire du numéro UNJCI dans members.current_member_number.
-- Ce script est idempotent et ne modifie ni ne supprime aucune donnée membre.
-- En présence de doublons, il les affiche et n'ajoute pas l'index unique.

SET NAMES utf8mb4;

SET @unjci_current_number_migration =
  '2026_08_12_120000_add_unique_index_to_current_member_number_on_members_table';

-- Affiche les valeurs à corriger manuellement avant de pouvoir créer l'index.
SELECT
  `current_member_number` AS `numero_unjci_duplique`,
  COUNT(*) AS `nombre_de_membres`
FROM `members`
WHERE `current_member_number` IS NOT NULL
GROUP BY `current_member_number`
HAVING COUNT(*) > 1
ORDER BY `current_member_number`;

SET @unjci_current_number_duplicate_groups = (
  SELECT COUNT(*)
  FROM (
    SELECT `current_member_number`
    FROM `members`
    WHERE `current_member_number` IS NOT NULL
    GROUP BY `current_member_number`
    HAVING COUNT(*) > 1
  ) AS `unjci_duplicate_current_numbers`
);

-- Un index unique existant sur cette seule colonne satisfait déjà la migration,
-- même s'il porte un autre nom.
SET @unjci_current_number_unique_index_before = (
  SELECT COUNT(*)
  FROM (
    SELECT `index_name`
    FROM `information_schema`.`statistics`
    WHERE BINARY `table_schema` = BINARY DATABASE()
      AND BINARY `table_name` = BINARY 'members'
      AND `non_unique` = 0
    GROUP BY `index_name`
    HAVING COUNT(*) = 1
       AND MAX(
         CASE
           WHEN BINARY `column_name` = BINARY 'current_member_number' THEN 1
           ELSE 0
         END
       ) = 1
  ) AS `unjci_current_number_unique_indexes`
);

SET @unjci_current_number_index_action = IF(
  @unjci_current_number_unique_index_before > 0,
  'SELECT ''Index unique déjà présent sur current_member_number'' AS information',
  IF(
    @unjci_current_number_duplicate_groups = 0,
    'CREATE UNIQUE INDEX `members_current_member_number_unique` ON `members` (`current_member_number`)',
    'SELECT ''Index non créé : corrigez manuellement les doublons affichés, puis réimportez ce fichier'' AS avertissement'
  )
);

PREPARE unjci_current_number_statement FROM @unjci_current_number_index_action;
EXECUTE unjci_current_number_statement;
DEALLOCATE PREPARE unjci_current_number_statement;

SET @unjci_current_number_unique_index_after = (
  SELECT COUNT(*)
  FROM (
    SELECT `index_name`
    FROM `information_schema`.`statistics`
    WHERE BINARY `table_schema` = BINARY DATABASE()
      AND BINARY `table_name` = BINARY 'members'
      AND `non_unique` = 0
    GROUP BY `index_name`
    HAVING COUNT(*) = 1
       AND MAX(
         CASE
           WHEN BINARY `column_name` = BINARY 'current_member_number' THEN 1
           ELSE 0
         END
       ) = 1
  ) AS `unjci_current_number_unique_indexes`
);

-- Laravel ne considérera la migration comme exécutée que si l'index est présent.
SET @unjci_current_number_batch = (
  SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT @unjci_current_number_migration, @unjci_current_number_batch
WHERE @unjci_current_number_unique_index_after > 0
  AND NOT EXISTS (
    SELECT 1
    FROM `migrations`
    WHERE BINARY `migration` = BINARY @unjci_current_number_migration
  );

SELECT
  @unjci_current_number_duplicate_groups AS `groupes_de_doublons_detectes`,
  IF(@unjci_current_number_unique_index_after > 0, 1, 0) AS `index_unique_installe`,
  (
    SELECT COUNT(*)
    FROM `migrations`
    WHERE BINARY `migration` = BINARY @unjci_current_number_migration
  ) AS `migration_enregistree`;
