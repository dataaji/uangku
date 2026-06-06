-- ============================================================
-- Uangku v6 — Jadwal terpadu (tanggal mulai + auto ingatkan + frekuensi)
-- Dipakai di: Tagihan (langganan & cicilan), Tabungan, Anggaran
-- Jalankan di phpMyAdmin (Import) atau MySQL CLI — aman diulang.
-- ============================================================
SET NAMES utf8mb4;
USE uangku;

-- Tagihan
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS mulai_tgl  DATE        DEFAULT NULL;
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS ingatkan   TINYINT(1)  DEFAULT 1;
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS frekuensi  VARCHAR(15) DEFAULT 'bulanan';
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS freq_hari  TINYINT     DEFAULT 1;   -- 0=Minggu .. 6=Sabtu
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS freq_tgl   TINYINT     DEFAULT 1;   -- 1..31
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS freq_bulan TINYINT     DEFAULT 1;   -- 1..12
ALTER TABLE tagihan ADD COLUMN IF NOT EXISTS selesai_tgl DATE       DEFAULT NULL;

-- Tabungan
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS mulai_tgl  DATE        DEFAULT NULL;
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS ingatkan   TINYINT(1)  DEFAULT 1;
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS frekuensi  VARCHAR(15) DEFAULT 'bulanan';
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS freq_hari  TINYINT     DEFAULT 1;
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS freq_tgl   TINYINT     DEFAULT 1;
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS freq_bulan TINYINT     DEFAULT 1;
ALTER TABLE tabungan ADD COLUMN IF NOT EXISTS selesai_tgl DATE       DEFAULT NULL;

-- Anggaran (frekuensi menggantikan kolom periode lama)
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS mulai_tgl  DATE        DEFAULT NULL;
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS ingatkan   TINYINT(1)  DEFAULT 1;
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS frekuensi  VARCHAR(15) DEFAULT 'bulanan';
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS freq_hari  TINYINT     DEFAULT 1;
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS freq_tgl   TINYINT     DEFAULT 1;
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS freq_bulan TINYINT     DEFAULT 1;
ALTER TABLE anggaran ADD COLUMN IF NOT EXISTS selesai_tgl DATE       DEFAULT NULL;

-- Selaraskan data lama: frekuensi mengikuti periode bila ada
UPDATE anggaran SET frekuensi = CASE periode
  WHEN 'harian' THEN 'harian' WHEN 'mingguan' THEN 'mingguan'
  WHEN 'tahunan' THEN 'tahunan' WHEN 'selamanya' THEN 'selamanya'
  ELSE 'bulanan' END
WHERE frekuensi IS NULL OR frekuensi='';
