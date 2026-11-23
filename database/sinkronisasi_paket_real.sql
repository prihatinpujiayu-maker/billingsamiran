-- ==============================================================================
-- SINKRONISASI DATA PAKET DAN RIWAYAT PERUBAHAN PAKET (UPGRADE / DOWNGRADE)
-- Multiwilayah Billing Samiran
-- ==============================================================================

-- 1. Sinkronisasi nama paket di tabel pelanggan_salam berdasarkan tarif_langganan aktif
UPDATE `pelanggan_salam`
SET `paket` = CASE
    WHEN `tarif_langganan` >= 150000 THEN 'Paket 150K'
    WHEN `tarif_langganan` >= 100000 THEN 'Paket 100K'
    WHEN `tarif_langganan` >= 85000 THEN 'Paket 85K'
    WHEN `tarif_langganan` >= 70000 THEN 'Paket 70K'
    WHEN `tarif_langganan` >= 50000 THEN 'Paket 50K'
    WHEN `tarif_langganan` > 0 THEN CONCAT('Paket Rp ', FORMAT(`tarif_langganan`, 0, 'de_DE'))
    ELSE '-'
END
WHERE `paket` = '-' OR `paket` = '' OR `paket` IS NULL OR `paket` = 'Belum diisi';

-- 2. Sinkronisasi paket_snapshot di tagihan_salam
UPDATE `tagihan_salam`
SET `paket_snapshot` = CASE
    WHEN `tarif_snapshot` >= 150000 THEN 'Paket 150K'
    WHEN `tarif_snapshot` >= 100000 THEN 'Paket 100K'
    WHEN `tarif_snapshot` >= 85000 THEN 'Paket 85K'
    WHEN `tarif_snapshot` >= 70000 THEN 'Paket 70K'
    WHEN `tarif_snapshot` >= 50000 THEN 'Paket 50K'
    WHEN `tarif_snapshot` > 0 THEN CONCAT('Paket Rp ', FORMAT(`tarif_snapshot`, 0, 'de_DE'))
    ELSE '-'
END
WHERE `paket_snapshot` = '-' OR `paket_snapshot` = '' OR `paket_snapshot` IS NULL OR `paket_snapshot` = 'Belum diisi';

-- 3. Memastikan riwayat_paket_salam memiliki data historis riil dari billing Samiran
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000395', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket ke 70K (catatan billing: down 70)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN000395'
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000378', p.nama, 'Paket 100K', 'Paket 150K', 100000.00, 150000.00, 50000.00, 'Upgrade', NOW(), '2026-04-01', 'Upgrade paket ke 150K (catatan billing: up 150)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN000378'
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000496', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket ke 70K (catatan billing: down70)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN000496'
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001426', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-07-01', 'Upgrade paket ke 100K (catatan billing: UP 100)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN001426'
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001645', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-07-01', 'Upgrade paket ke 100K (catatan billing: up 100)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN001645'
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001666', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-06-01', 'Upgrade paket ke 100K (catatan billing: up100)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN001666'
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001666', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-08-01', 'Downgrade paket ke 70K (catatan billing: down 70k)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN001666'
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002320', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-08-01', 'Upgrade paket ke 100K (catatan billing: up 100k)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN002320'
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002308', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket ke 70K (catatan billing: DOWN 70)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM pelanggan_salam p WHERE p.id_pelanggan = 'CMN002308'
ON DUPLICATE KEY UPDATE `id`=`id`;
