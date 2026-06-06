-- ============================================================
-- Migrasi v4 — Multi-user (tiap akun punya datanya sendiri)
-- Jalankan SEKALI di phpMyAdmin (database uangku)
-- ============================================================
SET NAMES utf8mb4;
USE uangku;

-- Tambah user_id ke semua tabel data (data lama → milik user 1 / demo)
ALTER TABLE dompet    ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;
ALTER TABLE transaksi ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;
ALTER TABLE anggaran  ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;
ALTER TABLE tabungan  ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;
ALTER TABLE tagihan   ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;
ALTER TABLE tugas     ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;
ALTER TABLE catatan   ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;
ALTER TABLE kategori  ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 1;

-- users: tambah kolom untuk login Google (opsional)
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(60) DEFAULT NULL;

-- notif dibaca: per user
DROP TABLE IF EXISTS notif_dibaca;
CREATE TABLE notif_dibaca (
  user_id   INT NOT NULL,
  notif_key VARCHAR(120) NOT NULL,
  dibaca_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, notif_key)
) ENGINE=InnoDB;
