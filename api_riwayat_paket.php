<?php
/**
 * API Riwayat Perubahan Paket Pelanggan (Upgrade / Downgrade / Penyesuaian Tarif)
 * Billing Samiran Multiwilayah
 */

session_start();
require_once __DIR__ . '/db_salam.php';
require_once __DIR__ . '/helpers_salam.php';
require_once __DIR__ . '/riwayat_paket_helper.php';

header('Content-Type: application/json; charset=utf-8');
salamRequireLogin();

function kirimJsonRiwayat(bool $success, string $message = '', array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'total' => count($data),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET: Ambil daftar riwayat perubahan paket pelanggan
if ($method === 'GET') {
    $pelangganId = (int) ($_GET['pelanggan_id'] ?? 0);
    if ($pelangganId <= 0) {
        kirimJsonRiwayat(false, 'Parameter pelanggan_id wajib diisi.');
    }

    $pelanggan = salamFindPelangganById($koneksi, $pelangganId);
    if (!$pelanggan) {
        kirimJsonRiwayat(false, 'Pelanggan tidak ditemukan atau di luar wilayah hak akses Anda.');
    }

    $rawList = salamGetSemuaRiwayatPaket($koneksi, $pelangganId);

    $formatted = [];
    foreach ($rawList as $row) {
        $tarifLama = (float) ($row['tarif_lama'] ?? 0);
        $tarifBaru = (float) ($row['tarif_baru'] ?? 0);
        $selisih = (float) ($row['selisih_tarif'] ?? ($tarifBaru - $tarifLama));
        $tglEfektif = (string) ($row['tanggal_efektif'] ?? '');
        $tglPerubahan = (string) ($row['tanggal_perubahan'] ?? '');

        $formatted[] = [
            'id' => (int) ($row['id'] ?? 0),
            'pelanggan_id' => $pelangganId,
            'id_pelanggan' => (string) ($row['id_pelanggan'] ?? ($pelanggan['id_pelanggan'] ?? '')),
            'nama_pelanggan' => (string) ($row['nama_pelanggan'] ?? ($pelanggan['nama'] ?? '')),
            'paket_lama' => (string) ($row['paket_lama'] ?? '-'),
            'paket_baru' => (string) ($row['paket_baru'] ?? '-'),
            'tarif_lama' => $tarifLama,
            'tarif_lama_rp' => salamRupiah($tarifLama),
            'tarif_baru' => $tarifBaru,
            'tarif_baru_rp' => salamRupiah($tarifBaru),
            'selisih_tarif' => $selisih,
            'selisih_tarif_rp' => ($selisih > 0 ? '+' : '') . salamRupiah($selisih),
            'jenis_perubahan' => (string) ($row['jenis_perubahan'] ?? salamTentukanJenisPerubahan($tarifLama, $tarifBaru)),
            'tanggal_perubahan' => $tglPerubahan !== '' ? $tglPerubahan : null,
            'tanggal_perubahan_label' => $tglPerubahan !== '' ? salamTanggalWaktuIndonesia($tglPerubahan) : 'Tidak tercatat (arsip historis)',
            'tanggal_efektif' => $tglEfektif,
            'tanggal_efektif_label' => $tglEfektif !== '' ? salamBulananIndonesia($tglEfektif, true) : '-',
            'alasan' => (string) ($row['alasan'] ?? '-'),
            'admin_nama' => (string) ($row['admin_nama'] ?? 'Admin'),
            'sumber' => (string) ($row['sumber'] ?? 'manual'),
            'sumber_label' => ($row['sumber'] ?? 'manual') === 'deteksi_historis' ? 'Deteksi Tagihan Historis' : 'Tercatat Langsung',
        ];
    }

    kirimJsonRiwayat(true, 'Riwayat perubahan paket berhasil diambil.', $formatted);
}

// POST: Tambah catatan perubahan paket (misal dari form modal mandiri)
if ($method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $pelangganId = (int) ($input['pelanggan_id'] ?? 0);
    $paketLama = trim((string) ($input['paket_lama'] ?? ''));
    $paketBaru = trim((string) ($input['paket_baru'] ?? ''));
    $tarifLama = (float) ($input['tarif_lama'] ?? 0);
    $tarifBaru = (float) ($input['tarif_baru'] ?? 0);
    $tanggalEfektif = trim((string) ($input['tanggal_efektif'] ?? date('Y-m-d')));
    $alasan = trim((string) ($input['alasan'] ?? ''));

    if ($pelangganId <= 0 || $paketBaru === '') {
        kirimJsonRiwayat(false, 'Parameter pelanggan_id dan paket_baru wajib diisi.');
    }

    $pelanggan = salamFindPelangganById($koneksi, $pelangganId);
    if (!$pelanggan) {
        kirimJsonRiwayat(false, 'Pelanggan tidak ditemukan.');
    }

    $ok = salamCatatRiwayatPaket($koneksi, [
        'pelanggan_id' => $pelangganId,
        'id_pelanggan' => (string) ($pelanggan['id_pelanggan'] ?? ''),
        'nama_pelanggan' => (string) ($pelanggan['nama'] ?? ''),
        'paket_lama' => $paketLama ?: (string) ($pelanggan['paket'] ?? '-'),
        'paket_baru' => $paketBaru,
        'tarif_lama' => $tarifLama > 0 ? $tarifLama : (float) ($pelanggan['tarif_langganan'] ?? 0),
        'tarif_baru' => $tarifBaru > 0 ? $tarifBaru : (float) ($pelanggan['tarif_langganan'] ?? 0),
        'tanggal_efektif' => $tanggalEfektif,
        'alasan' => $alasan,
        'admin_nama' => (string) ($_SESSION['username'] ?? 'Admin'),
        'sumber' => 'manual',
    ]);

    if ($ok) {
        kirimJsonRiwayat(true, 'Riwayat perubahan paket berhasil disimpan.');
    } else {
        kirimJsonRiwayat(false, 'Gagal menyimpan riwayat perubahan paket.');
    }
}

kirimJsonRiwayat(false, 'Metode HTTP tidak didukung.');
