-- ==============================================================================
-- SINKRONISASI DATA PAKET DAN RIWAYAT LENGKAP PERUBAHAN PAKET (UPGRADE / DOWNGRADE)
-- Sumber Data: Data Samiran.xlsx (Audit Lengkap 681 Pelanggan & 10 Periode Billing)
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

-- 3. Bersihkan data deteksi historis lama di riwayat_paket_salam sebelum sinkronisasi ulang
DELETE FROM `riwayat_paket_salam` WHERE `sumber` = 'deteksi_historis';

-- 4. Masukkan seluruh riwayat riil perubahan paket hasil audit spreadsheet Data Samiran.xlsx
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000395', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket ke 70K (catatan billing: down 70)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000395';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000358', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000358';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000378', p.nama, 'Paket 100K', 'Paket 150K', 100000.00, 150000.00, 50000.00, 'Upgrade', NOW(), '2026-04-01', 'Upgrade paket ke 150K (catatan billing: up 150)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000378';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000388', p.nama, 'Paket 150K', 'Paket 100K', 150000.00, 100000.00, -50000.00, 'Downgrade', NOW(), '2026-02-01', 'Downgrade paket dari 150K ke 100K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000388';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000398', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000398';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000486', p.nama, 'Paket 150K', 'Paket 100K', 150000.00, 100000.00, -50000.00, 'Downgrade', NOW(), '2026-05-01', 'Downgrade paket dari 150K ke 100K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000486';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000478', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-09-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000478';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000496', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket ke 70K (catatan billing: down70)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000496';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000500', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-04-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000500';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000513', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000513';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000560', p.nama, 'Paket 150K', 'Paket 100K', 150000.00, 100000.00, -50000.00, 'Downgrade', NOW(), '2026-05-01', 'Downgrade paket dari 150K ke 100K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000560';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000577', p.nama, 'Paket 150K', 'Paket 70K', 150000.00, 70000.00, -80000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 150K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000577';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000583', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-05-01', 'Upgrade paket dari 70K ke 100K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000583';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000665', p.nama, 'Paket 150K', 'Paket 100K', 150000.00, 100000.00, -50000.00, 'Downgrade', NOW(), '2026-02-01', 'Downgrade paket dari 150K ke 100K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000665';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000967', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-10-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000967';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000969', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-09-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000969';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000974', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000974';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000939', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-09-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000939';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN000943', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN000943';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001045', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-07-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001045';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001034', p.nama, 'Paket 150K', 'Paket 70K', 150000.00, 70000.00, -80000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 150K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001034';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001049', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-05-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001049';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001050', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001050';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001051', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001051';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001063', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001063';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001067', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001067';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001210', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-02-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001210';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001212', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001212';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001236', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-04-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001236';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001237', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-05-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001237';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001238', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001238';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001240', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001240';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001270', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001270';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001268', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-05-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001268';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001266', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-08-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001266';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001250', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001250';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001329', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-09-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001329';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001410', p.nama, 'Paket 70K', 'Paket 50K', 70000.00, 50000.00, -20000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 70K ke 50K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001410';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001413', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-03-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001413';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001414', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-05-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001414';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001419', p.nama, 'Paket 70K', 'Paket 50K', 70000.00, 50000.00, -20000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket dari 70K ke 50K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001419';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001421', p.nama, 'Paket 70K', 'Paket 50K', 70000.00, 50000.00, -20000.00, 'Downgrade', NOW(), '2026-08-01', 'Downgrade paket dari 70K ke 50K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001421';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001426', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-07-01', 'Upgrade paket ke 100K (catatan billing: UP 100)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001426';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001433', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-08-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001433';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001483', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-02-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001483';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001570', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001570';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001645', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-07-01', 'Upgrade paket ke 100K (catatan billing: up 100)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001645';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001648', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001648';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001650', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-02-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001650';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001654', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-07-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001654';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002228', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-04-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002228';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001666', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-06-01', 'Upgrade paket ke 100K (catatan billing: up100)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001666';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001666', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-08-01', 'Downgrade paket ke 70K (catatan billing: down 70k)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001666';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001595', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001595';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001667', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-10-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001667';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001669', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-02-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001669';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001670', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-08-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001670';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001671', p.nama, 'Paket 150K', 'Paket 70K', 150000.00, 70000.00, -80000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket dari 150K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001671';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001672', p.nama, 'Paket 150K', 'Paket 70K', 150000.00, 70000.00, -80000.00, 'Downgrade', NOW(), '2026-05-01', 'Downgrade paket dari 150K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001672';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001743', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-08-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001743';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001801', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001801';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001675', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001675';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001682', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001682';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002211', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-04-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002211';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001810', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001810';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001751', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-07-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001751';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN001766', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-08-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN001766';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002259', p.nama, 'Paket 150K', 'Paket 70K', 150000.00, 70000.00, -80000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 150K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002259';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002249', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-07-01', 'Upgrade paket dari 70K ke 100K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002249';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002289', p.nama, 'Paket 70K', 'Paket 50K', 70000.00, 50000.00, -20000.00, 'Downgrade', NOW(), '2026-09-01', 'Downgrade paket dari 70K ke 50K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002289';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002291', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-05-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002291';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002334', p.nama, 'Paket 150K', 'Paket 70K', 150000.00, 70000.00, -80000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 150K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002334';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002361', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-06-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002361';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002356', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-03-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002356';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002357', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-09-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002357';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002353', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-06-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002353';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002320', p.nama, 'Paket 70K', 'Paket 100K', 70000.00, 100000.00, 30000.00, 'Upgrade', NOW(), '2026-08-01', 'Upgrade paket ke 100K (catatan billing: up 100k)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002320';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002308', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-07-01', 'Downgrade paket ke 70K (catatan billing: DOWN 70)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002308';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002292', p.nama, 'Paket 50K', 'Paket 70K', 50000.00, 70000.00, 20000.00, 'Upgrade', NOW(), '2026-09-01', 'Upgrade paket dari 50K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002292';
INSERT INTO `riwayat_paket_salam`
(`pelanggan_id`, `id_pelanggan`, `nama_pelanggan`, `paket_lama`, `paket_baru`, `tarif_lama`, `tarif_baru`, `selisih_tarif`, `jenis_perubahan`, `tanggal_perubahan`, `tanggal_efektif`, `alasan`, `admin_nama`, `sumber`)
SELECT p.id, 'CMN002284', p.nama, 'Paket 100K', 'Paket 70K', 100000.00, 70000.00, -30000.00, 'Downgrade', NOW(), '2026-09-01', 'Downgrade paket dari 100K ke 70K (penyesuaian data billing spreadsheet)', 'Sistem (Data Billing)', 'deteksi_historis'
FROM `pelanggan_salam` p WHERE p.`id_pelanggan` = 'CMN002284';
