-- Settings/Profile additions.
-- Run only against existing databases that do not already have these columns.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone_num VARCHAR(20) NULL AFTER user_pfp,
    ADD COLUMN IF NOT EXISTS birthday DATE NULL AFTER phone_num;

ALTER TABLE user_address
    ADD COLUMN IF NOT EXISTS address_label VARCHAR(20) NOT NULL DEFAULT 'Home' AFTER user_id,
    ADD COLUMN IF NOT EXISTS is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER zip_code;
