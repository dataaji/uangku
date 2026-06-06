-- ============================================================
-- Migrasi v3 — jalankan SEKALI di phpMyAdmin (database uangku)
-- Aman diulang (IF NOT EXISTS)
-- ============================================================
SET NAMES utf8mb4;
USE uangku;

-- Anggaran: periode (harian/mingguan/bulanan/tahunan/selamanya) + status aktif
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS periode VARCHAR(15) DEFAULT 'bulanan';
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS aktif TINYINT(1) DEFAULT 1;

-- Tabungan: tanggal target tercapai
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS target_tanggal DATE DEFAULT NULL;

-- Tagihan: tenor cicilan (jumlah bulan)
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS tenor INT DEFAULT 0;
