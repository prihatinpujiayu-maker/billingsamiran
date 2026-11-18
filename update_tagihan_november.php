<?php
/**
 * Script update otomatis tagihan November berdasarkan Biaya Layanan Excel.
 * Bisa dijalankan langsung di browser: http://localhost/billingsamiran/update_tagihan_november.php
 */
require_once __DIR__ . '/db_salam.php';

header('Content-Type: text/html; charset=utf-8');

$sql = file_get_contents(__DIR__ . '/update_tagihan_november.sql');
if (!$sql) {
    die('File update_tagihan_november.sql tidak ditemukan.');
}

if ($koneksi->multi_query($sql)) {
    do {
        if ($result = $koneksi->store_result()) {
            $result->free();
        }
    } while ($koneksi->more_results() && $koneksi->next_result());
    echo "<div style='font-family:sans-serif;max-width:600px;margin:50px auto;padding:25px;border-radius:10px;background:#e8f5e9;border:1px solid #4caf50;'>";
    echo "<h2 style='color:#2e7d32;margin-top:0;'>Berhasil!</h2>";
    echo "<p>Data tarif langganan dan tagihan bulan November 2026 berhasil diperbarui berdasarkan kolom Biaya Layanan dari Excel.</p>";
    echo "<p><a href='dashboard_salam.php' style='display:inline-block;padding:10px 20px;background:#2e7d32;color:white;text-decoration:none;border-radius:5px;'>Kembali ke Dashboard</a></p>";
    echo "</div>";
} else {
    echo 'Gagal menjalankan query: ' . $koneksi->error;
}
