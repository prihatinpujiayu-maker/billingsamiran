<?php
require_once __DIR__ . '/../db_salam.php';
require_once __DIR__ . '/../riwayat_paket_helper.php';

$res = $koneksi->query("SELECT id, pelanggan_id, id_pelanggan, nama_pelanggan, paket_lama, paket_baru, tanggal_efektif, jenis_perubahan, alasan FROM riwayat_paket_salam LIMIT 5");
echo "Rows in riwayat_paket_salam:\n";
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']}, Pelanggan: {$r['id_pelanggan']} - {$r['nama_pelanggan']}, Efektif: {$r['tanggal_efektif']}, Jenis: {$r['jenis_perubahan']}\n";
}
