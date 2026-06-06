-- ============================================================
-- Uangku — Database Schema + Seed Data
-- Jalankan di phpMyAdmin atau MySQL CLI
-- ============================================================

-- Pastikan emoji tersimpan benar (jalankan apa pun klien-nya)
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS uangku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uangku;

-- ── Dompet / Wallet ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS dompet (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nama       VARCHAR(100)   NOT NULL,
  emoji      VARCHAR(10)    DEFAULT NULL,
  saldo      DECIMAL(15,2)  DEFAULT 0,
  created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Transaksi ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transaksi (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  emoji      VARCHAR(10)    DEFAULT NULL,
  tint       VARCHAR(20)    DEFAULT '#f7ecd5',
  judul      VARCHAR(200)   NOT NULL,
  kategori   VARCHAR(100)   DEFAULT 'Lainnya',
  dompet_id  INT,
  tanggal    DATE           NOT NULL,
  jumlah     DECIMAL(15,2)  NOT NULL COMMENT 'positif = masuk, negatif = keluar',
  catatan    TEXT,
  created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (dompet_id) REFERENCES dompet(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Anggaran / Budget ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS anggaran (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  emoji      VARCHAR(10)    DEFAULT NULL,
  kategori   VARCHAR(100)   NOT NULL,
  tint       VARCHAR(20)    DEFAULT '#f7ecd5',
  batas      DECIMAL(15,2)  NOT NULL,
  bulan      INT            NOT NULL COMMENT '1-12',
  tahun      INT            NOT NULL,
  created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Tabungan / Savings Goals ─────────────────────────────────
CREATE TABLE IF NOT EXISTS tabungan (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  emoji      VARCHAR(10)    DEFAULT NULL,
  judul      VARCHAR(200)   NOT NULL,
  tint       VARCHAR(20)    DEFAULT '#f7ecd5',
  terkumpul  DECIMAL(15,2)  DEFAULT 0,
  target     DECIMAL(15,2)  NOT NULL,
  per_bulan  DECIMAL(15,2)  DEFAULT 0,
  catatan    TEXT,
  warna      VARCHAR(20)    DEFAULT '#2f7d5d',
  created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Tagihan / Bills ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tagihan (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  emoji             VARCHAR(10)    DEFAULT NULL,
  tint              VARCHAR(20)    DEFAULT '#f7ecd5',
  nama              VARCHAR(200)   NOT NULL,
  deskripsi         VARCHAR(200),
  jumlah            DECIMAL(15,2)  NOT NULL,
  sudah_bayar       TINYINT(1)     DEFAULT 0,
  tgl_jatuh_tempo   INT            DEFAULT 1 COMMENT 'tanggal dalam bulan (1-31)',
  created_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Tugas / Tasks ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tugas (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  judul      VARCHAR(200)   NOT NULL,
  catatan    TEXT,
  tanggal    DATE,
  waktu      VARCHAR(10),
  prioritas  VARCHAR(20)    DEFAULT 'sedang' COMMENT 'tinggi, sedang, rendah',
  selesai    TINYINT(1)     DEFAULT 0,
  created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO dompet (nama, emoji, saldo) VALUES
  ('Tunai',    '💵',  1250000),
  ('Bank BCA', '🏦', 18420000),
  ('GoPay',    '📱',   842000);

INSERT INTO transaksi (emoji, tint, judul, kategori, dompet_id, tanggal, jumlah) VALUES
  ('💼', '#e4f0ea', 'Gaji Bulanan',      'Pemasukan', 2, '2026-06-01',  8500000),
  ('💡', '#f7ecd5', 'Token Listrik',     'Tagihan',   2, '2026-06-01',  -200000),
  ('🎬', '#ede4f4', 'Netflix',           'Hiburan',   2, '2026-06-01',   -65000),
  ('🛒', '#e3ecf6', 'Belanja Bulanan',   'Belanja',   2, '2026-06-02',  -485000),
  ('⛽', '#f6e4e1', 'Bensin Pertamax',   'Transport', 1, '2026-06-02',  -100000),
  ('🍜', '#f7ecd5', 'Makan Siang',       'Makanan',   3, '2026-06-03',   -38000),
  ('☕', '#f7e6da', 'Kopi Kenangan',     'Makanan',   3, '2026-06-03',   -22000),
  ('🚕', '#f6e4e1', 'Gojek ke Kantor',   'Transport', 3, '2026-05-31',   -28000),
  ('💊', '#e4f0ea', 'Apotek',            'Kesehatan', 1, '2026-05-30',   -54000),
  ('💼', '#e4f0ea', 'Gaji Bulanan',      'Pemasukan', 2, '2026-05-01',  8800000),
  ('🛒', '#e3ecf6', 'Belanja Mingguan',  'Belanja',   2, '2026-05-10',  -320000),
  ('🍜', '#f7ecd5', 'Makan Siang',       'Makanan',   3, '2026-05-15',   -45000),
  ('⛽', '#f6e4e1', 'Bensin',            'Transport', 1, '2026-05-20',   -80000),
  ('💡', '#f7ecd5', 'Token Listrik',     'Tagihan',   2, '2026-05-01',  -200000),
  ('💼', '#e4f0ea', 'Gaji Bulanan',      'Pemasukan', 2, '2026-04-01',  8500000),
  ('🛒', '#e3ecf6', 'Belanja',           'Belanja',   2, '2026-04-08',  -450000),
  ('🍜', '#f7ecd5', 'Makan',             'Makanan',   3, '2026-04-12',  -320000),
  ('💼', '#e4f0ea', 'Gaji Bulanan',      'Pemasukan', 2, '2026-03-01',  9100000),
  ('🎬', '#ede4f4', 'Netflix',           'Hiburan',   2, '2026-03-01',   -65000),
  ('💼', '#e4f0ea', 'Gaji Bulanan',      'Pemasukan', 2, '2026-02-01',  8500000),
  ('💼', '#e4f0ea', 'Gaji Bulanan',      'Pemasukan', 2, '2026-01-01',  8200000);

INSERT INTO anggaran (emoji, kategori, tint, batas, bulan, tahun) VALUES
  ('🍜', 'Makanan',   '#f7e6da', 1500000, 6, 2026),
  ('🛒', 'Belanja',   '#e3ecf6', 1200000, 6, 2026),
  ('🚗', 'Transport', '#f6e4e1',  700000, 6, 2026),
  ('💡', 'Tagihan',   '#f7ecd5',  750000, 6, 2026),
  ('🎬', 'Hiburan',   '#ede4f4',  300000, 6, 2026),
  ('💊', 'Kesehatan', '#e4f0ea',  500000, 6, 2026);

INSERT INTO tabungan (emoji, judul, tint, terkumpul, target, per_bulan, catatan, warna) VALUES
  ('🕋', 'Tabungan Haji',    '#e4f0ea', 28500000, 50000000, 1500000, 'Nabung Rp1,5jt/bln → 15 bulan lagi', '#2f7d5d'),
  ('✈️', 'Traveling Jepang', '#e3ecf6', 12000000, 35000000, 2000000, 'Perlu Rp2,3jt/bln untuk Apr 2027',   '#3b6fb0'),
  ('💻', 'Laptop Baru',      '#f7e6da',  6800000, 18000000, 1000000, 'Nabung Rp1jt/bln → 12 bulan lagi',   '#c8602c'),
  ('🚗', 'DP Mobil',         '#f7ecd5', 41000000, 60000000, 2500000, 'Nabung Rp2,5jt/bln → 8 bulan lagi',  '#d99a2b');

INSERT INTO tagihan (emoji, tint, nama, deskripsi, jumlah, sudah_bayar, tgl_jatuh_tempo) VALUES
  ('📶', '#e3ecf6', 'IndiHome',    'tiap tgl 5',   350000, 0,  5),
  ('🎬', '#ede4f4', 'Netflix',     'tiap tgl 1',    65000, 1,  1),
  ('🎵', '#e4f0ea', 'Spotify',     'tiap tgl 10',   54000, 0, 10),
  ('💡', '#f7ecd5', 'Listrik PLN', 'tiap tgl 20',  200000, 1, 20),
  ('💧', '#e3ecf6', 'PDAM Air',    'tiap tgl 15',   85000, 0, 15);

INSERT INTO tugas (judul, catatan, tanggal, waktu, prioritas, selesai) VALUES
  ('Bayar SPP anak',              'Transfer ke sekolah', '2026-06-05', '17.00', 'tinggi',  0),
  ('Beli kado ulang tahun',       'Budget Rp200rb',      '2026-06-05', '12.00', 'sedang',  0),
  ('Servis motor rutin',          'Bengkel langganan',   '2026-06-07', '09.30', 'sedang',  0),
  ('Catat pengeluaran mingguan',  '',                    '2026-06-08', '20.00', 'rendah',  0),
  ('Bayar listrik',               'Sudah lunas bulan ini','2026-06-01','10.00', 'rendah',  1);

-- ============================================================
-- TABEL v2 (users, kategori, catatan, dll) — auto
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

-- v3 additions
SET NAMES utf8mb4;
USE uangku;

-- Anggaran: periode (harian/mingguan/bulanan/tahunan/selamanya) + status aktif
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS periode VARCHAR(15) DEFAULT 'bulanan';
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS aktif TINYINT(1) DEFAULT 1;

-- Tabungan: tanggal target tercapai
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS target_tanggal DATE DEFAULT NULL;

-- Tagihan: tenor cicilan (jumlah bulan)
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS tenor INT DEFAULT 0;

-- v4 multi-user
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

-- v5
SET NAMES utf8mb4;
USE uangku;

-- Tabungan: pengingat bulanan + tanggal setor terakhir
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS ingat_tgl INT DEFAULT 0;          -- 0 = mati, 1-31 = tgl pengingat
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS terakhir_setor DATE DEFAULT NULL;

-- Anggaran: tanggal mulai (opsional, untuk periode custom)
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS tgl_mulai INT DEFAULT 0;          -- 0 = ikut periode
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS tgl_akhir INT DEFAULT 0;

-- Riwayat perubahan anggaran
CREATE TABLE IF NOT EXISTS anggaran_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  kategori VARCHAR(100),
  aksi VARCHAR(20),                -- buat | tambah | kurang | edit | hapus
  jumlah DECIMAL(15,2) DEFAULT 0,
  batas_baru DECIMAL(15,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
