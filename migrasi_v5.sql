-- ============================================================
-- Migrasi v5 — pengingat tabungan + riwayat anggaran
-- Jalankan SEKALI di phpMyAdmin (database uangku)
-- ============================================================
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
