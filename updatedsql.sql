


CREATE DATABASE IF NOT EXISTS zythera_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zythera_db;

-- ============================================================
-- ID COUNTER TABLE
-- Single-row-per-prefix counter; UPDATE + SELECT is atomic
-- per InnoDB row locking, so no duplicates under concurrency.
-- ============================================================
CREATE TABLE IF NOT EXISTS id_counters (
    prefix   VARCHAR(10)  NOT NULL,
    last_seq INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (prefix)
) ENGINE=InnoDB;

-- Seed one row per prefix so the stored procedure always finds
-- a row to UPDATE (avoids INSERT races).
INSERT IGNORE INTO id_counters (prefix, last_seq) VALUES
    ('OR',  0),   -- Orders          → OR-ZY001
    ('PAY', 0),   -- Payments        → PAY-ZY001
    ('U',   0),   -- Users           → U-ZY001
    ('AD',  0),   -- Admins          → AD-ZY001
    ('REV', 0),   -- Reviews         → REV-ZY001
    ('ODR', 0),   -- Order Items     → ODR-ZY001
    ('ADR', 0),   -- User Addresses  → ADR-ZY001
    ('CAT', 0),   -- Categories      → CAT-ZY001
    ('PRD', 0);   -- Product Inv     → PRD-ZY001

-- ============================================================
-- STORED PROCEDURE: generate_custom_id(prefix) → id_string
-- Example: CALL generate_custom_id('OR', @id);  → 'OR-ZY001'
-- ============================================================
DROP PROCEDURE IF EXISTS generate_custom_id;

DELIMITER $$
CREATE PROCEDURE generate_custom_id(
    IN  p_prefix VARCHAR(10),
    OUT p_id     VARCHAR(20)
)
BEGIN
    DECLARE v_seq INT UNSIGNED;

    -- Atomic increment (row lock held for this statement only)
    UPDATE id_counters SET last_seq = last_seq + 1 WHERE prefix = p_prefix;
    SELECT last_seq INTO v_seq FROM id_counters WHERE prefix = p_prefix;

    -- Format: PREFIX-ZYnnn  (zero-padded to 3 digits minimum)
    SET p_id = CONCAT(p_prefix, '-ZY', LPAD(v_seq, 3, '0'));
END$$
DELIMITER ;

-- ============================================================
-- 1. USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id      VARCHAR(20)  NOT NULL,
    fname        VARCHAR(50)  NOT NULL,
    mname        VARCHAR(50),
    lname        VARCHAR(50)  NOT NULL,
    email        VARCHAR(100) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    user_pfp     VARCHAR(255),
    date_created DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- 2. ADMINS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    admin_id    VARCHAR(20)  NOT NULL,
    admin_fname VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    admin_pfp   VARCHAR(500),
    PRIMARY KEY (admin_id)
) ENGINE=InnoDB;

-- ============================================================
-- 3. USER_ADDRESS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS user_address (
    address_id        VARCHAR(20)  NOT NULL,
    user_id           VARCHAR(20)  NOT NULL,
    phone_num         VARCHAR(20) NOT NULL,
    province          VARCHAR(100) NOT NULL,
    city_municipality VARCHAR(100) NOT NULL,
    barangay          VARCHAR(100) NOT NULL,
    st_address        VARCHAR(255) NOT NULL,
    zip_code          VARCHAR(10) NOT NULL,
    PRIMARY KEY (address_id),
    CONSTRAINT fk_ua_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. CATEGORY TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS category (
    category_id   VARCHAR(20)  NOT NULL,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    PRIMARY KEY (category_id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. PRODUCT_INV TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS product_inv (
    prod_id     VARCHAR(20)    NOT NULL,
    category_id VARCHAR(20)    NOT NULL,
    prod_name   VARCHAR(150)   NOT NULL,
    prod_desc   TEXT,
    prod_size   VARCHAR(100)   DEFAULT '',
    prod_color  VARCHAR(100)   DEFAULT '',
    prod_stock  INT            NOT NULL DEFAULT 0,
    unit_price  DECIMAL(10,2)  NOT NULL,
    img_url     VARCHAR(255),
    PRIMARY KEY (prod_id),
    CONSTRAINT fk_pi_category FOREIGN KEY (category_id) REFERENCES category (category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 6. PAYMENT TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS payment (
    payment_id     VARCHAR(20)  NOT NULL,
    payment_method VARCHAR(50)  NOT NULL,
    payment_status VARCHAR(50)  NOT NULL DEFAULT 'pending',
    payment_date   DATETIME,
    reference_no   VARCHAR(100),
    pay_proof      VARCHAR(255),
    PRIMARY KEY (payment_id)
) ENGINE=InnoDB;

-- ============================================================
-- 7. ORDERS TABLE
-- order_id IS the human-readable custom ID (e.g. OR-ZY001).
-- The old numeric order_id + separate order_ref are merged
-- into this single VARCHAR primary key.
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    order_id      VARCHAR(20)    NOT NULL,
    user_id       VARCHAR(20)    NOT NULL,
    address_id    VARCHAR(20)    NOT NULL,
    payment_id    VARCHAR(20)    NOT NULL,
    total_ammount DECIMAL(10,2)  NOT NULL,
    shipping_fee  DECIMAL(10,2)  NOT NULL DEFAULT 150.00,
    user_note     TEXT,
    order_date    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    order_status  VARCHAR(50)    NOT NULL DEFAULT 'Pending',
    PRIMARY KEY (order_id),
    CONSTRAINT fk_ord_user    FOREIGN KEY (user_id)    REFERENCES users        (user_id)    ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ord_address FOREIGN KEY (address_id) REFERENCES user_address (address_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ord_payment FOREIGN KEY (payment_id) REFERENCES payment      (payment_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 8. ORDER_ITEMS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    orderitem_id VARCHAR(20)    NOT NULL,
    order_id     VARCHAR(20)    NOT NULL,
    prod_id      VARCHAR(20)    NOT NULL,
    prod_name    VARCHAR(150)   NOT NULL,
    quantity     INT            NOT NULL DEFAULT 1,
    unit_price   DECIMAL(10,2)  NOT NULL,
    PRIMARY KEY (orderitem_id),
    CONSTRAINT fk_oi_order   FOREIGN KEY (order_id) REFERENCES orders      (order_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_oi_product FOREIGN KEY (prod_id)  REFERENCES product_inv (prod_id)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 9. REVIEWS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS reviews (
    review_id    VARCHAR(20) NOT NULL,
    orderitem_id VARCHAR(20) NOT NULL,
    order_id     VARCHAR(20) NOT NULL,
    user_id      VARCHAR(20) NOT NULL,
    user_rating  TINYINT     NOT NULL CHECK (user_rating BETWEEN 1 AND 5),
    user_review  TEXT,
    review_date  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    UNIQUE KEY uniq_review_order (order_id),
    CONSTRAINT fk_rev_orderitem FOREIGN KEY (orderitem_id) REFERENCES order_items (orderitem_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rev_order     FOREIGN KEY (order_id)     REFERENCES orders      (order_id)     ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rev_user      FOREIGN KEY (user_id)      REFERENCES users       (user_id)      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED CATEGORIES
-- ============================================================
CALL generate_custom_id('CAT', @cat1);
CALL generate_custom_id('CAT', @cat2);
CALL generate_custom_id('CAT', @cat3);

INSERT INTO category (category_id, category_name) VALUES
    (@cat1, 'Sofa'),
    (@cat2, 'Chair'),
    (@cat3, 'Set')
ON DUPLICATE KEY UPDATE category_id = category_id;

-- ============================================================
-- SEED ADMIN ACCOUNTS  (password: 123456qw)
-- ============================================================
CALL generate_custom_id('AD', @ad1);
CALL generate_custom_id('AD', @ad2);
CALL generate_custom_id('AD', @ad3);

INSERT INTO admins (admin_id, admin_fname, email, password, admin_pfp) VALUES
    (@ad1, 'Zythera Admin', 'zythera@gmail.com', '$2y$10$WHqvKNeQDyTM9lWhI0aYgewH8d872dzE3L/mruHcmQQHeDI0kouO.', 'pci/beti.jpg'),
    (@ad2, 'System Admin',  'admin@gmail.com',   '$2y$10$WHqvKNeQDyTM9lWhI0aYgewH8d872dzE3L/mruHcmQQHeDI0kouO.', 'pci/admin.jpg'),
    (@ad3, 'Mei',           'mei@gmail.com',     '$2y$10$WHqvKNeQDyTM9lWhI0aYgewH8d872dzE3L/mruHcmQQHeDI0kouO.', 'pci/mei.jpg')
ON DUPLICATE KEY UPDATE
    admin_fname = VALUES(admin_fname),
    password    = VALUES(password),
    admin_pfp   = VALUES(admin_pfp);

-- ============================================================
-- SEED PRODUCT INVENTORY
-- ============================================================
CALL generate_custom_id('PRD', @p01);
CALL generate_custom_id('PRD', @p02);
CALL generate_custom_id('PRD', @p03);
CALL generate_custom_id('PRD', @p04);
CALL generate_custom_id('PRD', @p05);
CALL generate_custom_id('PRD', @p06);
CALL generate_custom_id('PRD', @p07);
CALL generate_custom_id('PRD', @p08);
CALL generate_custom_id('PRD', @p09);
CALL generate_custom_id('PRD', @p10);

SET @cid_sofa  = (SELECT category_id FROM category WHERE category_name = 'Sofa'  LIMIT 1);
SET @cid_chair = (SELECT category_id FROM category WHERE category_name = 'Chair' LIMIT 1);

INSERT INTO product_inv (prod_id, category_id, prod_name, prod_desc, prod_size, prod_color, prod_stock, unit_price, img_url) VALUES
    (@p01, @cid_chair, 'Blue Accent Chair',              'Bold teal-blue upholstered accent chair with shell-shaped back and slim gold metal legs.',                                'L70 x W65 x H85 cm',   'Blue, Gold',            20,  7499.00, 'pci/images.jpeg'),
    (@p02, @cid_sofa,  'Industrial Gray Sectional Sofa', 'Modern L-shaped sectional sofa with coral/red accent cushions set in an industrial-style living space.',               'L260 x W160 x H90 cm', 'Gray, Red',              6, 29999.00, 'pci/image_6.png'),
    (@p03, @cid_chair, 'Beige Upholstered Dining Chairs','Classic high-back dining chairs with beige fabric upholstery and solid walnut wood legs.',                             'L58 x W52 x H88 cm',   'Beige, Walnut',         10,  9499.00, 'pci/download.jpeg'),
    (@p04, @cid_chair, 'Curved Cream Dining Chairs',     'Elegant curved-back upholstered dining chairs with natural oak legs, sold as a pair.',                                 'L60 x W55 x H82 cm',   'Cream, Oak',            15,  8999.00, 'pci/BUNKOR00195433_3_Supersize.jpg'),
    (@p05, @cid_sofa,  'Classic Tufted Sofa',            'Sleek channel-tufted sofa in taupe velvet fabric with slim tapered black legs and matching cushions.',                 'L200 x W90 x H85 cm',  'Taupe, Black',          12, 18499.00, 'pci/download_(4).jpeg'),
    (@p06, @cid_chair, 'Taupe Dining Chairs',            'Mid-century modern dining chairs with taupe faux leather upholstery and light wood tapered legs.',                     'L62 x W58 x H86 cm',   'Taupe, Light Oak',      14,  8499.00, 'pci/download_(2).jpeg'),
    (@p07, @cid_chair, 'Modern White Armchair',          'Contemporary molded plastic armchair with open back design and brushed metal legs.',                                   'L65 x W60 x H78 cm',   'White, Gray',           18,  5999.00, 'pci/image_2.png'),
    (@p08, @cid_sofa,  'Light Gray Sectional Sofa',      'Minimalist L-shaped sofa with plush light gray upholstery paired with a marble-top coffee table.',                    'L250 x W160 x H90 cm', 'Light Gray, Dark Metal',  5, 27499.00, 'pci/download_(5).jpeg'),
    (@p09, @cid_chair, 'Classic Dining Chair Set',       'High-back dining chairs with beige fabric upholstery and solid walnut wood legs, sold as a pair.',                    'L58 x W52 x H88 cm',   'Beige, Walnut',         10,  9499.00, 'pci/download.jpeg'),
    (@p10, @cid_sofa,  'Gray Metal Frame Sofa Set',      'Industrial-style living room sofa set with gray cushions, black metal frames, and matching armchairs and coffee table.','L220 x W80 x H85 cm', 'Gray, Black',            9, 24999.00, 'pci/images_(3).jpeg');
-- ============================================================
-- MIGRATION: Add pay_proof column to existing payment table
-- Safe to run on existing databases (IF NOT EXISTS equivalent)
-- ============================================================
ALTER TABLE payment
  ADD COLUMN IF NOT EXISTS pay_proof VARCHAR(255) DEFAULT NULL;

