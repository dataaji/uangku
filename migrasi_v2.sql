-- ============================================================
-- Migrasi ke Versi 2 — jalankan SEKALI di phpMyAdmin (database uangku)
-- Aman dijalankan ulang (pakai IF NOT EXISTS)
-- ============================================================
SET NAMES utf8mb4;
USE uangku;

-- ── Users (login) ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nama      VARCHAR(100) NOT NULL,
  email     VARCHAR(150) NOT NULL UNIQUE,
  password  VARCHAR(255) NOT NULL,
  pin       VARCHAR(255) DEFAULT NULL,
  dark_mode TINYINT(1) DEFAULT 0,
  avatar    VARCHAR(10) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Kategori (bisa ditambah sendiri seperti dompet) ─────────
CREATE TABLE IF NOT EXISTS kategori (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nama  VARCHAR(80) NOT NULL,
  emoji VARCHAR(10) DEFAULT NULL,
  tint  VARCHAR(20) DEFAULT '#fbf6ec',
  warna VARCHAR(20) DEFAULT '#5c5345',
  tipe  VARCHAR(10) DEFAULT 'keluar'
) ENGINE=InnoDB;

INSERT INTO kategori (nama, emoji, tint, warna, tipe) VALUES
  ('Makanan','🍜','#f7ecd5','#c8602c','keluar'),
  ('Belanja','🛒','#e3ecf6','#3b6fb0','keluar'),
  ('Transport','🚗','#f6e4e1','#c0392b','keluar'),
  ('Tagihan','💡','#f7ecd5','#d99a2b','keluar'),
  ('Hiburan','🎬','#ede4f4','#8a5fb0','keluar'),
  ('Kesehatan','💊','#e4f0ea','#2f7d5d','keluar'),
  ('Hadiah','🎁','#f7e6da','#c8602c','keluar'),
  ('Pemasukan','💼','#e4f0ea','#2f7d5d','masuk'),
  ('Lainnya','✏️','#fbf6ec','#5c5345','keluar');

-- ── Catatan / Agenda (per tanggal) ──────────────────────────
CREATE TABLE IF NOT EXISTS catatan (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  judul   VARCHAR(200) NOT NULL,
  isi     TEXT,
  warna   VARCHAR(20) DEFAULT '#8a5fb0',
  ingatkan TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Tagihan: kolom baru (langganan / cicilan / catatan) ─────
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS jenis     VARCHAR(20)   DEFAULT 'langganan';
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS total     DECIMAL(15,2) DEFAULT 0;
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS terbayar  DECIMAL(15,2) DEFAULT 0;
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS catatan   TEXT;
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS berulang  TINYINT(1)    DEFAULT 1;

-- ── Tabungan: pastikan kolom catatan ada ────────────────────
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS catatan TEXT;

-- ── Notifikasi yang sudah dibaca / dihapus ──────────────────
CREATE TABLE IF NOT EXISTS notif_dibaca (
  notif_key VARCHAR(120) PRIMARY KEY,
  dibaca_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
