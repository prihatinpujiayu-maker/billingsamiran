CREATE DATABASE IF NOT EXISTS database_billing;
USE database_billing;

-- 1. Tabel Users (Khusus Admin Samiran)
CREATE TABLE IF NOT EXISTS users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    wilayah VARCHAR(50) DEFAULT 'Samiran'
);

INSERT INTO users (username, password, role, wilayah) VALUES 
('admin_samiran', '$2y$10$P9zxnh.Jtb9Ta8pj8eRYpu3WpbOvMMFG74ozHAQS1R3P0mIz/zoza', 'admin', 'Samiran')
ON DUPLICATE KEY UPDATE password = '$2y$10$P9zxnh.Jtb9Ta8pj8eRYpu3WpbOvMMFG74ozHAQS1R3P0mIz/zoza', wilayah = 'Samiran';

-- 2. Tabel Master Wilayah / Dusun Samiran
CREATE TABLE IF NOT EXISTS master_wilayah_samiran (
    id_wilayah INT AUTO_INCREMENT PRIMARY KEY,
    nama_dusun VARCHAR(100) NOT NULL UNIQUE
);

INSERT IGNORE INTO master_wilayah_samiran (nama_dusun) VALUES 
('Samiran'), ('Suroteleng'), ('Lencoh'), ('Surodadi'), ('Sangkal'), 
('Bulu'), ('Bulurejo'), ('Tegalrejo'), ('Plalangan'), ('Spandan'), 
('Gancik'), ('Ngablak'), ('Tonggeng'), ('Pentongan'), ('Pojok'), 
('Tompak'), ('Cangkol'), ('Segaran'), ('Selo'), ('Ngaglik'), 
('Kuncen'), ('Gebyog'), ('Tretes'), ('Tegalsruni'), ('Rogobelah'), 
('Surjo'), ('Pulerejo'), ('Candi Baru'), ('Sidorejo'), ('Rejosari'), ('Temusari');

-- 3. Tabel Access Points / Pemancar
CREATE TABLE IF NOT EXISTS access_points (
    id_ap INT AUTO_INCREMENT PRIMARY KEY,
    identity_ap VARCHAR(100) NOT NULL UNIQUE,
    ssid VARCHAR(100),
    ip_ap VARCHAR(20),
    jenis_link ENUM('AP Bridge', 'PTP', 'PTM') DEFAULT 'AP Bridge'
);

INSERT IGNORE INTO access_points (identity_ap, ssid, ip_ap, jenis_link) VALUES
('SAMIRAN TIMUR 2/atas', 'Samiran Timur', '192.168.200.98', 'AP Bridge'),
('SAMIRAN SELATAN', 'SAMIRAN SELATAN', '192.168.200.97', 'AP Bridge'),
('SAMIRAN UTARA', 'PTM SAMIRAN UTARA', '192.168.200.95', 'AP Bridge'),
('PTM LENCOH SELATAN', 'PTM LENCOH SELATAN', '192.168.200.94', 'PTM'),
('PTM SUROTELENG BARAT', 'PTM SUROTELENG BARAT', '192.168.200.93', 'PTM'),
('SUROTELENG SELATAN', 'SUROTELENG SELATAN', '192.168.200.92', 'AP Bridge'),
('PTM SURODADI', 'PTM SURODADI', '192.168.200.91', 'PTM'),
('PTM SURODADI 2', 'PTM SURODADI 2', '192.168.200.90', 'PTM'),
('PTM SANGKAL', 'PTM SANGKAL', '192.168.200.85', 'PTM'),
('PTM BULU', 'PTM BULU', '192.168.200.84', 'PTM'),
('PTM LENCOH BARAT', 'PTM LENCOH BARAT', '192.168.200.76', 'PTM');

-- 4. Tabel Pelanggan Samiran (Gabungan Data Jaringan + Data Billing Excel)
DROP TABLE IF EXISTS tagihan_samiran;
DROP TABLE IF EXISTS pelanggan_samiran;

CREATE TABLE pelanggan_samiran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan VARCHAR(30) NOT NULL UNIQUE,
    nama_lengkap VARCHAR(100) NOT NULL,
    nik VARCHAR(30) NULL,
    id_wilayah INT NULL,
    alamat_lengkap TEXT NULL,
    no_wa VARCHAR(25) NULL,
    tgl_pasang VARCHAR(30) NULL,
    ppp_secret VARCHAR(100) NULL,
    ip_perangkat VARCHAR(20) NULL,
    mac_perangkat VARCHAR(25) NULL,
    id_ap INT NULL,
    kategori_link ENUM('End-User', 'PTP-Backhaul') DEFAULT 'End-User',
    paket VARCHAR(50) NULL,
    tarif_bulanan INT DEFAULT 0,
    biaya_instalasi INT DEFAULT 0,
    sisa_tagihan_2025 INT DEFAULT 0,
    status_pelanggan ENUM('Aktif', 'OFF Bulanan', 'Tidak Aktif') DEFAULT 'Aktif',
    riwayat_paket TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_wilayah) REFERENCES master_wilayah_samiran(id_wilayah) ON DELETE SET NULL,
    FOREIGN KEY (id_ap) REFERENCES access_points(id_ap) ON DELETE SET NULL
);

-- 5. Tabel Tagihan Samiran (Januari - Oktober 2026 & Seterusnya)
CREATE TABLE tagihan_samiran (
    id_tagihan INT AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan VARCHAR(30) NOT NULL,
    nomor_invoice VARCHAR(50) NOT NULL,
    periode_bulan VARCHAR(7) NOT NULL,
    tagihan_bulanan INT DEFAULT 0,
    tunggakan_2025 INT DEFAULT 0,
    biaya_instalasi INT DEFAULT 0,
    sewa_router INT DEFAULT 0,
    diskon INT DEFAULT 0,
    total_bayar INT DEFAULT 0,
    status_bayar ENUM('Belum Lunas', 'Lunas') DEFAULT 'Belum Lunas',
    tgl_bayar DATE NULL,
    keterangan_rapel VARCHAR(150) NULL,
    FOREIGN KEY (id_pelanggan) REFERENCES pelanggan_samiran(id_pelanggan) ON DELETE CASCADE
);

-- 6. Tabel Rekapitulasi Keuangan & Bagi Hasil Internal Samiran
CREATE TABLE IF NOT EXISTS rekap_kas_samiran (
    id_kas INT AUTO_INCREMENT PRIMARY KEY,
    periode_bulan VARCHAR(7) NOT NULL UNIQUE,
    total_pemasukan INT DEFAULT 0,
    bagi_hasil_penarik INT DEFAULT 0,
    biaya_operasional_5persen INT DEFAULT 0,
    setoran_instalasi INT DEFAULT 0,
    wifi_koin INT DEFAULT 0,
    upah_freelance INT DEFAULT 0,
    catatan TEXT
);