-- Installation manuelle du journal des connexions UNJCI.
-- Ce fichier peut être réimporté sans recréer la table ni dupliquer la migration.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `login_audits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `login` VARCHAR(255) NOT NULL,
  `success` TINYINT(1) NOT NULL,
  `failure_reason` VARCHAR(100) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_audits_user_id_foreign` (`user_id`),
  KEY `login_audits_login_index` (`login`),
  KEY `login_audits_created_at_index` (`created_at`),
  KEY `login_audits_success_created_at_index` (`success`, `created_at`),
  CONSTRAINT `login_audits_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Empêche Laravel de tenter de recréer la table lors d'un futur accès au terminal.
SET @unjci_login_audits_migration = '2026_08_11_190000_create_login_audits_table';
SET @unjci_login_audits_batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT @unjci_login_audits_migration, @unjci_login_audits_batch
WHERE NOT EXISTS (
  SELECT 1
  FROM `migrations`
  WHERE BINARY `migration` = BINARY @unjci_login_audits_migration
)
AND 9 = (
  SELECT COUNT(*)
  FROM `information_schema`.`columns`
  WHERE BINARY `table_schema` = BINARY DATABASE()
    AND BINARY `table_name` = BINARY 'login_audits'
    AND (
      BINARY `column_name` = BINARY 'id'
      OR BINARY `column_name` = BINARY 'user_id'
      OR BINARY `column_name` = BINARY 'login'
      OR BINARY `column_name` = BINARY 'success'
      OR BINARY `column_name` = BINARY 'failure_reason'
      OR BINARY `column_name` = BINARY 'ip_address'
      OR BINARY `column_name` = BINARY 'user_agent'
      OR BINARY `column_name` = BINARY 'created_at'
      OR BINARY `column_name` = BINARY 'updated_at'
    )
);

SELECT COUNT(*) AS `connexions_deja_journalisees` FROM `login_audits`;
