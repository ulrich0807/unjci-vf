-- A exécuter une seule fois dans phpMyAdmin avant de publier le renouvellement.
ALTER TABLE payments
    ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'adhesion' AFTER transaction_id,
    ADD COLUMN previous_member_number VARCHAR(255) NULL AFTER payment_type,
    ADD COLUMN old_member_card_path VARCHAR(255) NULL AFTER previous_member_number;

ALTER TABLE members
    ADD COLUMN membership_expires_at DATE NULL AFTER status;
