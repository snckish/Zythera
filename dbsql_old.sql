CREATE DATABASE IF NOT EXISTS zythera_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zythera_db;

-- ============================================
-- 1. USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    user_id      INT          NOT NULL AUTO_INCREMENT,
    fname        VARCHAR(50)  NOT NULL,
    mname        VARCHAR(50),
    lname        VARCHAR(50)  NOT NULL,
    email        VARCHAR(100) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    user_pfp     VARCHAR(255),
    date_created DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB;

-- ============================================
-- 2. ADMINS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS admins (
    admin_id    INT          NOT NULL AUTO_INCREMENT,
    admin_fname VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    admin_pfp   VARCHAR(500),
    PRIMARY KEY (admin_id)
) ENGINE=InnoDB;

-- ============================================
-- 3. USER_ADDRESS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS user_address (
    address_id        INT          NOT NULL AUTO_INCREMENT,
    user_id           INT          NOT NULL,
    phone_num         VARCHAR(20),
    st_address        VARCHAR(255),
    barangay          VARCHAR(100),
    city_municipality VARCHAR(100),
    province          VARCHAR(100),
    zip_code          VARCHAR(10),
    PRIMARY KEY (address_id),
    CONSTRAINT fk_ua_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 4. CATEGORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS category (
    category_id   INT          NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (category_id)
) ENGINE=InnoDB;

-- ============================================
-- 5. PRODUCT_INV TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS product_inv (
    prod_id     INT            NOT NULL AUTO_INCREMENT,
    category_id INT            NOT NULL,
    prod_name   VARCHAR(150)   NOT NULL,
    prod_desc   TEXT,
    prod_size   VARCHAR(100)   DEFAULT '',
    prod_color  VARCHAR(100)   DEFAULT '',
    prod_stock  INT            NOT NULL DEFAULT 0,
    unit_price  DECIMAL(10, 2) NOT NULL,
    img_url     VARCHAR(255),
    PRIMARY KEY (prod_id),
    CONSTRAINT fk_pi_category FOREIGN KEY (category_id) REFERENCES category (category_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- 6. PAYMENT TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS payment (
    payment_id     INT          NOT NULL AUTO_INCREMENT,
    payment_method VARCHAR(50)  NOT NULL,
    payment_status VARCHAR(50)  NOT NULL DEFAULT 'pending',
    payment_date   DATETIME,
    reference_no   VARCHAR(100),
    PRIMARY KEY (payment_id)
) ENGINE=InnoDB;

-- ============================================
-- 7. ORDERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS orders (
    order_id      INT            NOT NULL AUTO_INCREMENT,
    order_ref     VARCHAR(50)    NOT NULL UNIQUE,
    user_id       INT            NOT NULL,
    address_id    INT            NOT NULL,
    payment_id    INT            NOT NULL,
    total_ammount DECIMAL(10, 2) NOT NULL,
    shipping_fee  DECIMAL(10, 2) NOT NULL DEFAULT 150.00,
    user_note     TEXT,
    order_date    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    order_status  VARCHAR(50)    NOT NULL DEFAULT 'pending',
    PRIMARY KEY (order_id),
    CONSTRAINT fk_ord_user    FOREIGN KEY (user_id)    REFERENCES users        (user_id)    ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ord_address FOREIGN KEY (address_id) REFERENCES user_address (address_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ord_payment FOREIGN KEY (payment_id) REFERENCES payment      (payment_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- 8. ORDER_ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS order_items (
    orderitem_id INT            NOT NULL AUTO_INCREMENT,
    order_id     INT            NOT NULL,
    prod_id      INT            NOT NULL,
    prod_name    VARCHAR(150)   NOT NULL,
    quantity     INT            NOT NULL DEFAULT 1,
    unit_price   DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (orderitem_id),
    CONSTRAINT fk_oi_order   FOREIGN KEY (order_id) REFERENCES orders      (order_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_oi_product FOREIGN KEY (prod_id)  REFERENCES product_inv (prod_id)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- 9. REVIEWS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS reviews (
    review_id    INT      NOT NULL AUTO_INCREMENT,
    orderitem_id INT      NOT NULL,
    order_id     INT      NOT NULL,
    user_id      INT      NOT NULL,
    user_rating  TINYINT  NOT NULL CHECK (user_rating BETWEEN 1 AND 5),
    user_review  TEXT,
    admin_reply  TEXT,
    reply_date   DATETIME,
    review_date  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    UNIQUE KEY uniq_review_order (order_id),
    CONSTRAINT fk_rev_orderitem FOREIGN KEY (orderitem_id) REFERENCES order_items (orderitem_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rev_order     FOREIGN KEY (order_id)     REFERENCES orders      (order_id)      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rev_user      FOREIGN KEY (user_id)      REFERENCES users        (user_id)      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- 10. MESSAGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS messages (
    msg_id      INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          DEFAULT NULL,
    full_name   VARCHAR(150),
    email       VARCHAR(150),
    subject     VARCHAR(200),
    msg_content TEXT         NOT NULL,
    admin_reply TEXT,
    reply_date  DATETIME,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (msg_id),
    CONSTRAINT fk_msg_user FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- SEED CATEGORIES
-- ============================================
INSERT INTO category (category_name) VALUES
('Sofa'), ('Chair'), ('Set')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- ============================================
-- SEED ADMIN ACCOUNTS (PASSWORD: 123456qw)
-- ============================================
INSERT INTO admins (admin_fname, email, password, admin_pfp) VALUES
('Zythera Admin', 'zythera@gmail.com', '$2y$10$WHqvKNeQDyTM9lWhI0aYgewH8d872dzE3L/mruHcmQQHeDI0kouO.', 'pci/beti.jpg'),
('System Admin',  'admin@gmail.com',   '$2y$10$WHqvKNeQDyTM9lWhI0aYgewH8d872dzE3L/mruHcmQQHeDI0kouO.', 'pci/admin.jpg'),
('Mei',           'mei@gmail.com',     '$2y$10$WHqvKNeQDyTM9lWhI0aYgewH8d872dzE3L/mruHcmQQHeDI0kouO.', 'pci/mei.jpg')
ON DUPLICATE KEY UPDATE
    admin_fname = VALUES(admin_fname),
    password    = VALUES(password),
    admin_pfp   = VALUES(admin_pfp);

-- ============================================
-- SEED INVENTORY (product_inv)
-- Category IDs: 1=Sofa, 2=Chair, 3=Set
-- ============================================
INSERT INTO product_inv (category_id, prod_name, prod_desc, prod_size, prod_color, prod_stock, unit_price, img_url) VALUES
(2, 'Blue Accent Chair', 'Bold teal-blue upholstered accent chair with shell-shaped back and slim gold metal legs.', 'L70 x W65 x H85 cm', 'Blue, Gold', 20, 7499.00, 'pci/images.jpeg'),
(1, 'Industrial Gray Sectional Sofa', 'Modern L-shaped sectional sofa with coral/red accent cushions set in an industrial-style living space.', 'L260 x W160 x H90 cm', 'Gray, Red', 6, 29999.00, 'pci/image_6.png'),
(2, 'Beige Upholstered Dining Chairs', 'Classic high-back dining chairs with beige fabric upholstery and solid walnut wood legs.', 'L58 x W52 x H88 cm', 'Beige, Walnut', 10, 9499.00, 'pci/download.jpeg'),
(2, 'Curved Cream Dining Chairs', 'Elegant curved-back upholstered dining chairs with natural oak legs, sold as a pair.', 'L60 x W55 x H82 cm', 'Cream, Oak', 15, 8999.00, 'pci/BUNKOR00195433_3_Supersize.jpg'),
(1, 'Classic Tufted Sofa', 'Sleek channel-tufted sofa in taupe velvet fabric with slim tapered black legs and matching cushions.', 'L200 x W90 x H85 cm', 'Taupe, Black', 12, 18499.00, 'pci/download_(4).jpeg'),
(2, 'Taupe Dining Chairs', 'Mid-century modern dining chairs with taupe faux leather upholstery and light wood tapered legs.', 'L62 x W58 x H86 cm', 'Taupe, Light Oak', 14, 8499.00, 'pci/download_(2).jpeg'),
(2, 'Modern White Armchair', 'Contemporary molded plastic armchair with open back design and brushed metal legs.', 'L65 x W60 x H78 cm', 'White, Gray', 18, 5999.00, 'pci/image_2.png'),
(1, 'Light Gray Sectional Sofa', 'Minimalist L-shaped sofa with plush light gray upholstery paired with a marble-top coffee table.', 'L250 x W160 x H90 cm', 'Light Gray, Dark Metal', 5, 27499.00, 'pci/download_(5).jpeg'),
(2, 'Classic Dining Chair Set', 'High-back dining chairs with beige fabric upholstery and solid walnut wood legs, sold as a pair.', 'L58 x W52 x H88 cm', 'Beige, Walnut', 10, 9499.00, 'pci/download.jpeg'),
(1, 'Gray Metal Frame Sofa Set', 'Industrial-style living room sofa set with gray cushions, black metal frames, and matching armchairs and coffee table.', 'L220 x W80 x H85 cm', 'Gray, Black', 9, 24999.00, 'pci/images_(3).jpeg');
