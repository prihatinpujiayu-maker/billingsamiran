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

// GET: Ambil daftar riwayat perubahan paket pelanggan atau detail single item
if ($method === 'GET') {
    $singleId = (int) ($_GET['id'] ?? 0);
    if ($singleId > 0) {
        $item = salamGetRiwayatPaketById($koneksi, $singleId);
        if (!$item) {
            kirimJsonRiwayat(false, 'Catatan riwayat paket tidak ditemukan.');
        }
        kirimJsonRiwayat(true, 'Detail riwayat paket berhasil diambil.', $item);
    }

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

// POST: Tambah, Perbarui (Edit), atau Hapus catatan perubahan paket
if ($method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = trim((string) ($input['action'] ?? ''));
    $id = (int) ($input['id'] ?? 0);

    // 1. AKSI HAPUS (DELETE)
    if ($action === 'delete') {
        if ($id <= 0) {
            kirimJsonRiwayat(false, 'ID catatan riwayat tidak valid.');
        }

        $existing = salamGetRiwayatPaketById($koneksi, $id);
        if (!$existing) {
            kirimJsonRiwayat(false, 'Catatan riwayat tidak ditemukan atau sudah dihapus.');
        }

        $ok = salamHapusRiwayatPaket($koneksi, $id);
        if ($ok) {
            kirimJsonRiwayat(true, 'Catatan riwayat perubahan paket berhasil dihapus.');
        } else {
            kirimJsonRiwayat(false, 'Gagal menghapus catatan riwayat perubahan paket.');
        }
    }

    // 2. AKSI PERBARUI / EDIT (UPDATE)
    if ($action === 'update' || ($id > 0 && ($action === 'edit' || $action === 'save'))) {
        if ($id <= 0) {
            kirimJsonRiwayat(false, 'ID catatan riwayat wajib disertakan untuk pembaruan.');
        }

        $existing = salamGetRiwayatPaketById($koneksi, $id);
        if (!$existing) {
            kirimJsonRiwayat(false, 'Catatan riwayat tidak ditemukan.');
        }

        $ok = salamUpdateRiwayatPaket($koneksi, $id, [
            'paket_lama' => trim((string) ($input['paket_lama'] ?? $existing['paket_lama'])),
            'paket_baru' => trim((string) ($input['paket_baru'] ?? $existing['paket_baru'])),
            'tarif_lama' => isset($input['tarif_lama']) ? (float) $input['tarif_lama'] : (float) $existing['tarif_lama'],
            'tarif_baru' => isset($input['tarif_baru']) ? (float) $input['tarif_baru'] : (float) $existing['tarif_baru'],
            'jenis_perubahan' => trim((string) ($input['jenis_perubahan'] ?? '')),
            'tanggal_efektif' => trim((string) ($input['tanggal_efektif'] ?? $existing['tanggal_efektif'])),
            'alasan' => trim((string) ($input['alasan'] ?? $existing['alasan'])),
            'admin_nama' => (string) ($_SESSION['username'] ?? 'Admin'),
        ]);

        if ($ok) {
            kirimJsonRiwayat(true, 'Catatan riwayat perubahan paket berhasil diperbarui.');
        } else {
            kirimJsonRiwayat(false, 'Gagal memperbarui catatan riwayat perubahan paket.');
        }
    }

    // 3. AKSI TAMBAH BARU (CREATE)
    $pelangganId = (int) ($input['pelanggan_id'] ?? 0);
    $paketLama = trim((string) ($input['paket_lama'] ?? ''));
    $paketBaru = trim((string) ($input['paket_baru'] ?? ''));
    $tarifLama = (float) ($input['tarif_lama'] ?? 0);
    $tarifBaru = (float) ($input['tarif_baru'] ?? 0);
    $tanggalEfektif = trim((string) ($input['tanggal_efektif'] ?? date('Y-m-d')));
    $alasan = trim((string) ($input['alasan'] ?? ''));
    $jenisPerubahan = trim((string) ($input['jenis_perubahan'] ?? ''));

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
        'jenis_perubahan' => $jenisPerubahan ?: null,
        'tanggal_efektif' => $tanggalEfektif,
        'alasan' => $alasan,
        'admin_nama' => (string) ($_SESSION['username'] ?? 'Admin'),
        'sumber' => 'manual',
    ]);

    if ($ok) {
        kirimJsonRiwayat(true, 'Catatan riwayat perubahan paket berhasil disimpan.');
    } else {
        kirimJsonRiwayat(false, 'Gagal menyimpan riwayat perubahan paket.');
    }
}

kirimJsonRiwayat(false, 'Metode HTTP tidak didukung.');
