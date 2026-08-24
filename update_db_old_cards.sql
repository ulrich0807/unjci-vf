-- Exécutez cette requête dans votre outil de gestion de base de données (ex: phpMyAdmin) 
-- pour mettre à jour la table members sur votre serveur en ligne.

ALTER TABLE `members`
ADD COLUMN `old_card_recto_path` VARCHAR(255) NULL AFTER `press_card_verso_path`,
ADD COLUMN `old_card_verso_path` VARCHAR(255) NULL AFTER `old_card_recto_path`;
