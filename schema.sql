-- =====================================================================
--  CaseZone — iPhone Accessories Online Ordering System
--  MySQL 8 / MariaDB schema  (import in phpMyAdmin or: mysql -u root < schema.sql)
--  Currency: Philippine Peso (PHP ₱) · Payment: Cash on Delivery
-- =====================================================================

CREATE DATABASE IF NOT EXISTS casezone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE casezone;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items, orders, products, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- USERS  (admin / rider / client)
-- New sign-ups are always 'client'. The admin promotes riders.
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(120)  NOT NULL,
  email         VARCHAR(190)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,            -- use PHP password_hash()
  phone         VARCHAR(30)   NULL,
  address       VARCHAR(255)  NULL,
  role          ENUM('admin','rider','client') NOT NULL DEFAULT 'client',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PRODUCTS
-- ---------------------------------------------------------------------
CREATE TABLE products (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(150)  NOT NULL,
  description       TEXT          NOT NULL,
  category          VARCHAR(60)   NOT NULL,
  price             DECIMAL(10,2) NOT NULL,
  compare_at_price  DECIMAL(10,2) NULL,
  stock             INT UNSIGNED  NOT NULL DEFAULT 0,
  image_url         VARCHAR(255)  NULL,
  compatible_models VARCHAR(120)  NOT NULL DEFAULT 'iPhone 13 – iPhone 17 series',
  is_featured       TINYINT(1)    NOT NULL DEFAULT 0,
  is_active         TINYINT(1)    NOT NULL DEFAULT 1,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_category (category),
  CHECK (price >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ORDERS
-- status flow: pending -> confirmed -> preparing -> out_for_delivery -> delivered
--              (pending orders can be cancelled by the client)
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id      INT UNSIGNED NOT NULL,
  rider_id         INT UNSIGNED NULL,
  status           ENUM('pending','confirmed','preparing','out_for_delivery','delivered','cancelled')
                   NOT NULL DEFAULT 'pending',
  subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee     DECIMAL(10,2) NOT NULL DEFAULT 0,   -- ₱79, free when subtotal >= ₱2,000
  total            DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method   ENUM('cod') NOT NULL DEFAULT 'cod',
  delivery_name    VARCHAR(120) NOT NULL,
  delivery_phone   VARCHAR(30)  NOT NULL,
  delivery_address VARCHAR(255) NOT NULL,
  notes            VARCHAR(255) NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_orders_rider    FOREIGN KEY (rider_id)    REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_status (status)
) ENGINE=InnoDB AUTO_INCREMENT=10001;

-- ---------------------------------------------------------------------
-- ORDER ITEMS  (price snapshot at time of order)
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  quantity     INT UNSIGNED NOT NULL,
  line_total   DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SAMPLE PRODUCTS
-- ---------------------------------------------------------------------
INSERT INTO products (name, description, category, price, compare_at_price, stock, image_url, is_featured) VALUES
('Aurora Clear MagSafe Case','Crystal-clear, anti-yellowing shell with a built-in MagSafe ring and sky-tinted edges.','Cases',1290,1590,40,'products/aurora-clear-case.jpg',1),
('Cloud Silicone Case','Liquid silicone with a velvet microfiber lining in a soft-touch sky blue finish.','Cases',990,NULL,60,'products/cloud-silicone-case.jpg',1),
('Heritage Leather Wallet Folio','Full-grain leather folio with three card slots and a magnetic clasp.','Cases',1890,2190,25,'products/leather-folio-case.jpg',0),
('Titan Armor Rugged Case','Dual-layer armor with raised camera guard and a fold-out kickstand.','Cases',1490,NULL,35,'products/titan-armor-case.jpg',0),
('Diamond 9H Glass (2-Pack)','Ultra-clear tempered glass with oleophobic coating and auto-align frame.','Screen Protection',590,790,120,'products/tempered-glass.jpg',0),
('Sapphire Lens Guard','Sapphire-coated rings that protect each camera lens.','Screen Protection',450,NULL,80,'products/lens-guard.jpg',0),
('Nimbus 20W USB-C Charger','Compact GaN fast charger with foldable prongs.','Chargers',890,1090,70,'products/usb-c-charger.jpg',0),
('Halo MagSafe Wireless Charger','15W magnetic wireless charging puck with braided cable.','Chargers',1690,NULL,45,'products/magsafe-charger.jpg',1),
('Skyline 3-in-1 Charging Stand','Charge iPhone, Apple Watch and AirPods at once.','Chargers',3490,3990,20,'products/charging-stand.jpg',1),
('Braided USB-C Cable 2m','Double-braided nylon cable rated for 30,000 bends.','Cables',490,NULL,150,'products/braided-cable.jpg',0),
('Drift MagSafe Power Bank','5,000mAh magnetic battery that snaps onto your iPhone.','Power',1990,2390,30,'products/power-bank.jpg',1),
('Zone Pods Pro','Noise-cancelling wireless earbuds with 30-hour battery.','Audio',2990,3490,25,'products/earbuds.jpg',1);

-- ---------------------------------------------------------------------
-- FIRST ADMIN: sign up normally on the site, then run:
--   UPDATE users SET role = 'admin' WHERE email = 'you@example.com';
-- Make someone a rider:
--   UPDATE users SET role = 'rider' WHERE email = 'rider@example.com';
-- ---------------------------------------------------------------------