-- ============================================================
-- Uangku v7 — Dompet auto-reset (tanggal reset + uang baru)
-- Aman diulang.
-- ============================================================
SET NAMES utf8mb4;
USE uangku;

ALTER TABLE dompet ADD COLUMN IF NOT EXISTS reset_tgl       INT           DEFAULT 0;   -- 0 = mati, 1-31 = tanggal reset
ALTER TABLE dompet ADD COLUMN IF NOT EXISTS uang_baru       DECIMAL(15,2) DEFAULT 0;   -- saldo yang masuk saat reset
ALTER TABLE dompet ADD COLUMN IF NOT EXISTS reset_terakhir  DATE          DEFAULT NULL;
