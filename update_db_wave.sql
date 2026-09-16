-- Requête SQL pour la mise à jour de la base de données de production
-- Ajout de la colonne 'checkout_session_id' pour l'API Wave

ALTER TABLE `payments` ADD `checkout_session_id` VARCHAR(255) NULL DEFAULT NULL AFTER `transaction_id`;
