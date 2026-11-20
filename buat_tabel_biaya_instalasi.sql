
USE billing_samiran;

CREATE TABLE biaya_instalasi_samiran (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pelanggan_id INT(10) UNSIGNED NOT NULL,
    nominal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status_bayar ENUM('Lunas','Belum Lunas')
        NOT NULL DEFAULT 'Belum Lunas',
    tanggal_instalasi DATE DEFAULT NULL,
    tanggal_bayar DATE DEFAULT NULL,
    keterangan TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unik_biaya_pelanggan (pelanggan_id),
    INDEX idx_status_bayar (status_bayar),

    CONSTRAINT fk_instalasi_pelanggan
        FOREIGN KEY (pelanggan_id)
        REFERENCES pelanggan_salam(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
