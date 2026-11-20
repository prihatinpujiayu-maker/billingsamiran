
<?php
session_start();

require_once __DIR__ . '/db_salam.php';
require_once __DIR__ . '/helpers_salam.php';

salamRequireLogin();

header('Content-Type: application/json; charset=utf-8');

function kirimJson($success, $message = '', $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function tanggalValid($tanggal)
{
    if ($tanggal === '' || $tanggal === null) {
        return true;
    }

    $date = DateTime::createFromFormat('Y-m-d', $tanggal);
    return $date && $date->format('Y-m-d') === $tanggal;
}

$method = $_SERVER['REQUEST_METHOD'];

// AMBIL DATA BIAYA INSTALASI
if ($method === 'GET') {
    $sql = "
        SELECT
            b.id,
            b.pelanggan_id,
            p.id_pelanggan,
            p.nama,
            p.alamat,
            b.nominal,
            b.status_bayar,
            b.tanggal_instalasi,
            b.tanggal_bayar,
            b.keterangan
        FROM biaya_instalasi_samiran b
        INNER JOIN pelanggan_salam p ON p.id = b.pelanggan_id
        ORDER BY b.id DESC
    ";

    $result = $koneksi->query($sql);

    if (!$result) {
        kirimJson(false, 'Gagal mengambil data: ' . $koneksi->error);
    }

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    kirimJson(true, 'Data berhasil diambil.', $data);
}

// PROSES SIMPAN / UBAH DATA
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    // SIMPAN BIAYA INSTALASI
    if ($action === 'simpan') {
        $pelangganId = (int) ($_POST['pelanggan_id'] ?? 0);
        $nominal = (float) ($_POST['nominal'] ?? 0);
        $status = $_POST['status_bayar'] ?? 'Belum Lunas';
        $tanggalInstalasi = trim($_POST['tanggal_instalasi'] ?? '');
        $tanggalBayar = trim($_POST['tanggal_bayar'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($pelangganId <= 0 || $nominal <= 0) {
            kirimJson(false, 'Pelanggan dan nominal biaya wajib diisi.');
        }

        if (!in_array($status, ['Lunas', 'Belum Lunas'], true)) {
            kirimJson(false, 'Status pembayaran tidak valid.');
        }

        if (!tanggalValid($tanggalInstalasi) || !tanggalValid($tanggalBayar)) {
            kirimJson(false, 'Format tanggal tidak valid.');
        }

        if ($tanggalInstalasi === '') {
            $tanggalInstalasi = null;
        }

        if ($status === 'Lunas') {
            if ($tanggalBayar === '') {
                $tanggalBayar = date('Y-m-d');
            }
        } else {
            $tanggalBayar = null;
        }

        $cek = $koneksi->prepare(
            "SELECT id FROM pelanggan_salam WHERE id = ?"
        );
        $cek->bind_param('i', $pelangganId);
        $cek->execute();
        $pelangganAda = $cek->get_result()->num_rows > 0;
        $cek->close();

        if (!$pelangganAda) {
            kirimJson(false, 'Data pelanggan tidak ditemukan.');
        }

        $sql = "
            INSERT INTO biaya_instalasi_samiran
                (pelanggan_id, nominal, status_bayar,
                 tanggal_instalasi, tanggal_bayar, keterangan)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nominal = VALUES(nominal),
                status_bayar = VALUES(status_bayar),
                tanggal_instalasi = VALUES(tanggal_instalasi),
                tanggal_bayar = VALUES(tanggal_bayar),
                keterangan = VALUES(keterangan)
        ";

        $stmt = $koneksi->prepare($sql);

        if (!$stmt) {
            kirimJson(false, 'Gagal menyiapkan penyimpanan data.');
        }

        $stmt->bind_param(
            'idssss',
            $pelangganId,
            $nominal,
            $status,
            $tanggalInstalasi,
            $tanggalBayar,
            $keterangan
        );

        if (!$stmt->execute()) {
            kirimJson(false, 'Gagal menyimpan biaya instalasi: ' . $stmt->error);
        }

        $stmt->close();
        kirimJson(true, 'Biaya instalasi berhasil disimpan.');
    }

    // UBAH STATUS PEMBAYARAN
    if ($action === 'ubah_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status_bayar'] ?? '';

        if ($id <= 0 || !in_array($status, ['Lunas', 'Belum Lunas'], true)) {
            kirimJson(false, 'Data perubahan status tidak valid.');
        }

        if ($status === 'Lunas') {
            $sql = "
                UPDATE biaya_instalasi_samiran
                SET status_bayar = 'Lunas',
                    tanggal_bayar = CURDATE()
                WHERE id = ?
            ";
        } else {
            $sql = "
                UPDATE biaya_instalasi_samiran
                SET status_bayar = 'Belum Lunas',
                    tanggal_bayar = NULL
                WHERE id = ?
            ";
        }

        $stmt = $koneksi->prepare($sql);
        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            kirimJson(false, 'Gagal mengubah status pembayaran.');
        }

        if ($stmt->affected_rows === 0) {
            $cek = $koneksi->prepare(
                "SELECT id FROM biaya_instalasi_samiran WHERE id = ?"
            );
            $cek->bind_param('i', $id);
            $cek->execute();
            $ada = $cek->get_result()->num_rows > 0;
            $cek->close();

            if (!$ada) {
                kirimJson(false, 'Data biaya instalasi tidak ditemukan.');
            }
        }

        $stmt->close();
        kirimJson(true, 'Status pembayaran berhasil diperbarui.');
    }

    kirimJson(false, 'Perintah tidak dikenal.');
}

kirimJson(false, 'Metode permintaan tidak diizinkan.');
