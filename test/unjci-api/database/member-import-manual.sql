-- Mise à niveau manuelle pour la base de production UNJCI.
-- À sauvegarder puis exécuter une seule fois avant l'import du tableau des anciens membres.

DELIMITER $$

DROP PROCEDURE IF EXISTS `unjci_add_column_if_missing`$$
CREATE PROCEDURE `unjci_add_column_if_missing`(
    IN table_name_value VARCHAR(64),
    IN column_name_value VARCHAR(64),
    IN column_definition_value TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_value
          AND COLUMN_NAME = column_name_value
    ) THEN
        SET @sql_statement = CONCAT(
            'ALTER TABLE `', table_name_value, '` ADD COLUMN `', column_name_value, '` ', column_definition_value
        );
        PREPARE statement_to_run FROM @sql_statement;
        EXECUTE statement_to_run;
        DEALLOCATE PREPARE statement_to_run;
    END IF;
END$$

CALL `unjci_add_column_if_missing`('members', 'alias', 'VARCHAR(255) NULL AFTER `first_name`')$$
CALL `unjci_add_column_if_missing`('members', 'member_number', 'VARCHAR(255) NULL AFTER `current_member_number`')$$
CALL `unjci_add_column_if_missing`('members', 'media_name', 'VARCHAR(255) NULL AFTER `employers`')$$
CALL `unjci_add_column_if_missing`('members', 'media_type', 'VARCHAR(20) NULL AFTER `media_name`')$$
DROP PROCEDURE `unjci_add_column_if_missing`$$

DROP PROCEDURE IF EXISTS `unjci_drop_column_if_present`$$
CREATE PROCEDURE `unjci_drop_column_if_present`(
    IN table_name_value VARCHAR(64),
    IN column_name_value VARCHAR(64)
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_value
          AND COLUMN_NAME = column_name_value
    ) THEN
        SET @sql_statement = CONCAT(
            'ALTER TABLE `', table_name_value, '` DROP COLUMN `', column_name_value, '`'
        );
        PREPARE statement_to_run FROM @sql_statement;
        EXECUTE statement_to_run;
        DEALLOCATE PREPARE statement_to_run;
    END IF;
END$$
CALL `unjci_drop_column_if_present`('members', 'press_card_file_path')$$
CALL `unjci_drop_column_if_present`('members', 'cv_file_path')$$
DROP PROCEDURE `unjci_drop_column_if_present`$$

DROP PROCEDURE IF EXISTS `unjci_add_member_number_index`$$
CREATE PROCEDURE `unjci_add_member_number_index`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'members'
          AND INDEX_NAME = 'members_member_number_unique'
    ) THEN
        CREATE UNIQUE INDEX `members_member_number_unique` ON `members` (`member_number`);
    END IF;
END$$
CALL `unjci_add_member_number_index`()$$
DROP PROCEDURE `unjci_add_member_number_index`$$

DELIMITER ;

CREATE TABLE IF NOT EXISTS `preloaded_members` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(255) NOT NULL,
    `suggested_last_name` VARCHAR(255) NULL,
    `suggested_first_names` VARCHAR(255) NULL,
    `media_name` VARCHAR(255) NULL,
    `company_name` VARCHAR(255) NULL,
    `media_type` VARCHAR(20) NULL,
    `press_card_number` VARCHAR(255) NULL,
    `member_number` VARCHAR(255) NOT NULL,
    `mapping_status` VARCHAR(20) NOT NULL DEFAULT 'unmatched',
    `member_id` BIGINT UNSIGNED NULL,
    `claimed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `preloaded_members_press_card_number_unique` (`press_card_number`),
    UNIQUE KEY `preloaded_members_member_number_unique` (`member_number`),
    UNIQUE KEY `preloaded_members_member_id_unique` (`member_id`),
    CONSTRAINT `preloaded_members_member_id_foreign`
        FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Après contrôle des données existantes, les numéros doivent respecter :
-- CIJP : 0454JP
-- UNJCI : UJ25-00122
