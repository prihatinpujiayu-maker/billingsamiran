<?php
/**
 * API Diskon Pelanggan
 * Billing Samiran Multiwilayah
 */

session_start();
require_once __DIR__ . '/db_salam.php';
require_once __DIR__ . '/helpers_salam.php';
require_once __DIR__ . '/diskon_helper.php';

header('Content-Type: application/json; charset=utf-8');
salamRequireLogin();

function kirimJsonDiskon(bool $success, string $message = '', array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim((string) ($input['action'] ?? ''));
    $id = (int) ($input['id'] ?? 0);

    // Hapus Diskon berdasarkan ID
    if ($action === 'delete') {
        if ($id <= 0) {
            kirimJsonDiskon(false, 'ID diskon tidak valid.');
        }

        $ok = salamHapusDiskonPelangganById($koneksi, $id);
        if ($ok) {
            kirimJsonDiskon(true, 'Catatan diskon berhasil dihapus.');
        } else {
            kirimJsonDiskon(false, 'Gagal menghapus catatan diskon.');
        }
    }

    // Simpan / Perbarui Diskon
    $pelangganId = (int) ($input['pelanggan_id'] ?? 0);
    if ($pelangganId <= 0) {
        kirimJsonDiskon(false, 'Parameter pelanggan_id wajib diisi.');
    }

    $pelanggan = salamFindPelangganById($koneksi, $pelangganId);
    if (!$pelanggan) {
        kirimJsonDiskon(false, 'Pelanggan tidak ditemukan.');
    }

    $periode = trim((string) ($input['periode'] ?? date('Y-m')));
    $nominal = (float) ($input['nominal_diskon'] ?? 0);
    $keterangan = trim((string) ($input['keterangan'] ?? ''));
    $tarifNormal = (float) ($input['tarif_normal'] ?? ($pelanggan['tarif_langganan'] ?? 0));

    $ok = salamCatatDiskonPelanggan($koneksi, [
        'pelanggan_id' => $pelangganId,
        'id_pelanggan' => (string) ($pelanggan['id_pelanggan'] ?? ''),
        'nama_pelanggan' => (string) ($pelanggan['nama'] ?? ''),
        'periode' => $periode,
        'nominal_diskon' => $nominal,
        'keterangan' => $keterangan,
        'tarif_normal' => $tarifNormal,
        'tarif_setelah_diskon' => max(0, $tarifNormal - $nominal),
        'admin_nama' => (string) ($_SESSION['username'] ?? 'Admin'),
        'sumber' => 'manual',
    ]);

    if ($ok) {
        kirimJsonDiskon(true, 'Catatan diskon berhasil disimpan.');
    } else {
        kirimJsonDiskon(false, 'Gagal menyimpan catatan diskon.');
    }
}

kirimJsonDiskon(false, 'Metode HTTP tidak didukung.');
