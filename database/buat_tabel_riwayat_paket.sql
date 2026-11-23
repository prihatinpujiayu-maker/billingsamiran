-- ============================================================================
-- Tabel Riwayat Perubahan Paket Pelanggan (Upgrade/Downgrade/Penyesuaian Tarif)
-- Project: Billing Samiran Multiwilayah
-- ============================================================================

CREATE TABLE IF NOT EXISTS `riwayat_paket_salam` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `pelanggan_id` INT UNSIGNED NOT NULL COMMENT 'Mengacu secara logis ke pelanggan_salam.id',
  `id_pelanggan` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'Snapshot ID pelanggan',
  `nama_pelanggan` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Snapshot Nama pelanggan',
  `paket_lama` VARCHAR(180) NOT NULL COMMENT 'Nama paket sebelumnya',
  `paket_baru` VARCHAR(180) NOT NULL COMMENT 'Nama paket setelah perubahan',
  `tarif_lama` DECIMAL(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Tarif paket lama',
  `tarif_baru` DECIMAL(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Tarif paket baru',
  `selisih_tarif` DECIMAL(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Selisih (tarif_baru - tarif_lama)',
  `jenis_perubahan` ENUM('Upgrade','Downgrade','Penyesuaian Tarif') NOT NULL COMMENT 'Jenis perubahan paket',
  `tanggal_perubahan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu dilakukannya perubahan',
  `tanggal_efektif` DATE NOT NULL COMMENT 'Tanggal mulai berlakunya paket baru',
  `alasan` TEXT DEFAULT NULL COMMENT 'Alasan / keterangan perubahan',
  `admin_id` INT NULL DEFAULT NULL COMMENT 'ID administrator jika ada',
  `admin_nama` VARCHAR(100) DEFAULT NULL COMMENT 'Username administrator yang melakukan perubahan',
  `sumber` ENUM('manual','deteksi_historis') NOT NULL DEFAULT 'manual' COMMENT 'Asal pencatatan: manual saat edit atau deteksi_historis',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pelanggan_id` (`pelanggan_id`),
  INDEX `idx_tanggal_efektif` (`tanggal_efektif`),
  INDEX `idx_jenis_perubahan` (`jenis_perubahan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
