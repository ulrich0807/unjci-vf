-- Mise à niveau manuelle du nouveau parcours d'adhésion UNJCI.
-- À importer depuis phpMyAdmin lorsque le terminal/SSH n'est pas disponible.
-- Le script est idempotent : il peut être relancé sans recréer les colonnes.

SET NAMES utf8mb4;

SET @unjci_has_application_submitted_at = (
  SELECT COUNT(*)
  FROM `information_schema`.`columns`
  WHERE BINARY `table_schema` = BINARY DATABASE()
    AND BINARY `table_name` = BINARY 'members'
    AND BINARY `column_name` = BINARY 'application_submitted_at'
);

SET @unjci_add_application_submitted_at = IF(
  @unjci_has_application_submitted_at = 0,
  'ALTER TABLE `members` ADD COLUMN `application_submitted_at` TIMESTAMP NULL DEFAULT NULL AFTER `status`',
  'SELECT ''La colonne application_submitted_at existe déjà'' AS information'
);
PREPARE unjci_statement FROM @unjci_add_application_submitted_at;
EXECUTE unjci_statement;
DEALLOCATE PREPARE unjci_statement;

SET @unjci_has_approved_at = (
  SELECT COUNT(*)
  FROM `information_schema`.`columns`
  WHERE BINARY `table_schema` = BINARY DATABASE()
    AND BINARY `table_name` = BINARY 'members'
    AND BINARY `column_name` = BINARY 'approved_at'
);

SET @unjci_add_approved_at = IF(
  @unjci_has_approved_at = 0,
  'ALTER TABLE `members` ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `application_submitted_at`',
  'SELECT ''La colonne approved_at existe déjà'' AS information'
);
PREPARE unjci_statement FROM @unjci_add_approved_at;
EXECUTE unjci_statement;
DEALLOCATE PREPARE unjci_statement;

-- Enregistre la migration afin qu'elle ne soit pas rejouée si un accès SSH est ajouté plus tard.
SET @unjci_workflow_migration = '2026_08_11_200000_add_application_workflow_timestamps_to_members_table';
SET @unjci_workflow_batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT @unjci_workflow_migration, @unjci_workflow_batch
WHERE NOT EXISTS (
  SELECT 1
  FROM `migrations`
  WHERE BINARY `migration` = BINARY @unjci_workflow_migration
)
AND 2 = (
  SELECT COUNT(*)
  FROM `information_schema`.`columns`
  WHERE BINARY `table_schema` = BINARY DATABASE()
    AND BINARY `table_name` = BINARY 'members'
    AND (
      BINARY `column_name` = BINARY 'application_submitted_at'
      OR BINARY `column_name` = BINARY 'approved_at'
    )
);

SELECT
  (SELECT COUNT(*) FROM `information_schema`.`columns`
   WHERE BINARY `table_schema` = BINARY DATABASE()
     AND BINARY `table_name` = BINARY 'members'
     AND BINARY `column_name` = BINARY 'application_submitted_at') AS `application_submitted_at_installee`,
  (SELECT COUNT(*) FROM `information_schema`.`columns`
   WHERE BINARY `table_schema` = BINARY DATABASE()
     AND BINARY `table_name` = BINARY 'members'
     AND BINARY `column_name` = BINARY 'approved_at') AS `approved_at_installee`;
