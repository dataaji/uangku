-- ============================================================
-- Uangku v8 — Catatan berulang + pilihan waktu pengingat
-- Aman diulang.
-- ============================================================
SET NAMES utf8mb4;
USE uangku;

ALTER TABLE catatan ADD COLUMN IF NOT EXISTS ulang      VARCHAR(15) DEFAULT 'tidak';  -- tidak | tahunan | bulanan | mingguan
ALTER TABLE catatan ADD COLUMN IF NOT EXISTS ingat_lead VARCHAR(10) DEFAULT 'hari';   -- hari | minggu | bulan (berapa lama sebelum hari-H)
