-- ANNA Manager — skema database (REFERENSI SAJA)
-- Kamu TIDAK perlu import ini manual. db.php membuat database & tabel otomatis
-- saat aplikasi pertama dibuka. File ini hanya untuk dokumentasi.

CREATE DATABASE IF NOT EXISTS anna_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE anna_manager;

CREATE TABLE IF NOT EXISTS products (
  id VARCHAR(32) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  cat VARCHAR(100) DEFAULT 'Umum',
  gram INT DEFAULT 1,
  harga INT DEFAULT 0,
  hpp INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stores (
  id VARCHAR(32) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  contact VARCHAR(120) DEFAULT '',
  address VARCHAR(255) DEFAULT ''
) ENGINE=InnoDB;

-- Master bahan baku + riwayat harga (untuk bandingkan naik/turun)
CREATE TABLE IF NOT EXISTS materials (
  id VARCHAR(32) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  unit VARCHAR(50) DEFAULT 'kg',
  price INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS material_prices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  material_id VARCHAR(32),
  pdate DATE,
  price INT DEFAULT 0,
  source VARCHAR(20) DEFAULT 'manual',
  INDEX(material_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batches (
  id VARCHAR(32) PRIMARY KEY,
  bdate DATE,
  note TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batch_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id VARCHAR(32),
  material_id VARCHAR(32) DEFAULT NULL,
  name VARCHAR(255),
  qty DECIMAL(12,3) DEFAULT 0,
  unit VARCHAR(50) DEFAULT '',
  price INT DEFAULT 0,
  INDEX(batch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batch_ops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id VARCHAR(32),
  name VARCHAR(255),
  amount INT DEFAULT 0,
  INDEX(batch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batch_outputs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id VARCHAR(32),
  product_id VARCHAR(32),
  qty INT DEFAULT 0,
  INDEX(batch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS distributions (
  id VARCHAR(32) PRIMARY KEY,
  ddate DATE,
  store_id VARCHAR(32),
  product_id VARCHAR(32),
  qty INT DEFAULT 0,
  harga INT DEFAULT 0,
  hpp INT DEFAULT 0,
  INDEX(store_id), INDEX(product_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  distribution_id VARCHAR(32),
  pdate DATE,
  amount INT DEFAULT 0,
  note VARCHAR(255) DEFAULT '',
  INDEX(distribution_id)
) ENGINE=InnoDB;
